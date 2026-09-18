<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\WorkOrders;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderAttachmentService;
use App\Domain\WorkOrders\Services\WorkOrderPdfService;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\WorkOrders\BulkUpdateWorkOrderStatusRequest;
use App\Http\Requests\Web\WorkOrders\StoreWorkOrderAttachmentRequest;
use App\Http\Requests\Web\WorkOrders\StoreWorkOrderRequest;
use App\Http\Requests\Web\WorkOrders\UpdateWorkOrderRequest;
use App\Models\Company;
use App\Models\NumberingPattern;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WorkOrderController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderAttachmentService $attachments,
        private readonly NumberingPatternService $numberingPatterns,
        private readonly DocumentChatService $chats,
        private readonly WorkOrderPdfService $workOrderPdf,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrder::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'pending' => $request->has('pending')
                ? $request->string('pending')->trim()->toString()
                : '1',
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('WorkOrders/Index', [
            'filters' => $filters,
            'statusOptions' => $this->workOrders->statusOptions(WorkOrderStage::WorkOrder),
            'can' => [
                'create' => $request->user()?->can('create', WorkOrder::class) ?? false,
                'update' => $request->user()?->can('work_orders.update') ?? false,
                'delete' => $request->user()?->can('work_orders.delete') ?? false,
                'configure_pattern' => ($request->user()?->can('create', NumberingPattern::class) ?? false)
                    || ($request->user()?->can('numbering_patterns.update') ?? false),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);

        $owner = $this->activeCompany($request);
        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'subject', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'pending', 'created_from', 'created_to', 'establishment_id', 'contract_id'],
        );
        $filters['is_work_order'] = true;

        $scopedToEstablishment = (int) ($filters['establishment_id'] ?? 0) > 0;
        $scopedToContract = (int) ($filters['contract_id'] ?? 0) > 0;

        if (! $scopedToEstablishment && ! $scopedToContract && ! $request->has('pending') && ($filters['pending'] ?? '') === '') {
            $filters['pending'] = '1';
        }

        return TabulatorResponse::fromPaginator(
            $this->workOrders->paginateForWeb($owner, $filters),
        );
    }

    public function downloadPdf(WorkOrder $workOrder): HttpResponse
    {
        $this->authorize('view', $workOrder);

        return $this->workOrderPdf->stream($workOrder);
    }

    public function bulkStatus(BulkUpdateWorkOrderStatusRequest $request): JsonResponse
    {
        $owner = $this->activeCompany($request);
        $validated = $request->validated();

        $result = $this->workOrders->bulkChangeStatus(
            $owner,
            array_map('intval', $validated['ids']),
            (int) $validated['status_id'],
            WorkOrderStage::WorkOrder,
            $request->user(),
            isset($validated['status_justification']) ? (string) $validated['status_justification'] : null,
        );

        return response()->json($result);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WorkOrder::class);

        $owner = $this->activeCompany($request);
        $stage = WorkOrderStage::WorkOrder;
        $suggestedCode = $this->numberingPatterns->peekNext($owner, NumberingResource::WorkOrders->value);

        $defaults = $this->workOrders->defaultsFromContract(
            $owner,
            $request->integer('contract_id') ?: null,
            $request->integer('establishment_id') ?: null,
        );

        return Inertia::render('WorkOrders/Create', [
            'suggestedCode' => $suggestedCode,
            'codeIsAutomatic' => $suggestedCode !== null,
            'defaultStatusId' => $this->workOrders->defaultStatusId($stage),
            'defaultEstablishmentId' => $defaults['establishment_id'],
            'defaultContractId' => $defaults['contract_id'],
            'defaultSubject' => $defaults['subject'],
            ...$this->formOptions($owner, $stage),
        ]);
    }

    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $workOrder = $this->workOrders->create($owner, $request->validated());

        return redirect()
            ->route('work-orders.edit', $workOrder)
            ->with('success', 'work_order_created_successfully');
    }

    public function edit(Request $request, WorkOrder $workOrder): Response
    {
        $this->authorize('update', $workOrder);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $workOrder->loadMissing('status');
        $canViewAttachments = $user?->can('viewAttachments', $workOrder) ?? false;
        $canViewPrivateAttachments = $user?->can('viewPrivateAttachments', $workOrder) ?? false;
        $canUpdateClosed = $user?->can('updateClosed', $workOrder) ?? false;
        $isOpen = (bool) ($workOrder->status?->is_open ?? true);
        $lifecycle = $workOrder->status?->lifecycle !== null ? (int) $workOrder->status->lifecycle : null;
        // Prod CicloVidaEstadosOtEnum::FINALIZADA_PENDIENTE = 4 — intervention becomes non-editable.
        $interventionLocked = $lifecycle !== null && $lifecycle >= 4;
        // Prod: open lifecycle (<= FINALIZADA_PENDIENTE) + abierto + establecimiento update permission.
        $canChangeEstablishment = $isOpen
            && ($lifecycle === null || $lifecycle <= 4)
            && ($user?->can('update', $workOrder) ?? false);
        $stage = WorkOrderStage::WorkOrder;

        return Inertia::render('WorkOrders/Edit', [
            'workOrder' => $this->workOrders->toFormData($workOrder),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForWorkOrder($workOrder, $canViewPrivateAttachments)
                : [],
            ...$this->formOptions($owner, $stage, $workOrder),
            'fields_locked' => ! $isOpen && ! $canUpdateClosed,
            'intervention_locked' => $interventionLocked,
            'can_change_establishment' => $canChangeEstablishment,
            'related_estimate_url' => $workOrder->is_estimate
                ? route('estimates.edit', $workOrder)
                : null,
            'chat' => $user !== null
                ? $this->chats->payload(ChatDocumentType::WorkOrder, (int) $workOrder->id, $user)
                : null,
            'can' => [
                'delete' => $user?->can('delete', $workOrder) ?? false,
                'update_closed' => $canUpdateClosed,
                'view_attachments' => $canViewAttachments,
                'view_private_attachments' => $canViewPrivateAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $workOrder) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $workOrder) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $workOrder) ?? false,
                'post_chat' => $user?->can('update', $workOrder) ?? false,
            ],
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $result = $this->workOrders->update($owner, $workOrder, $request->validated(), $request->user());
        $clone = $result['cloned_estimate'];

        if ($clone !== null) {
            return redirect()
                ->route('estimates.edit', $clone)
                ->with('success', 'work_order_estimate_created_successfully');
        }

        return redirect()
            ->route('work-orders.edit', $result['work_order'])
            ->with('success', 'work_order_updated_successfully');
    }

    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('delete', $workOrder);

        $this->workOrders->delete($workOrder);

        return redirect()
            ->route('work-orders.index')
            ->with('success', 'work_order_deleted_successfully');
    }

    public function storeAttachment(StoreWorkOrderAttachmentRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->attachments->store($workOrder, $user, $file, $request->boolean('is_private'));

        return redirect()
            ->route('work-orders.edit', ['work_order' => $workOrder, 'tab' => 'attachments'])
            ->with('success', 'work_order_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Request $request, WorkOrder $workOrder, WorkOrderAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachments', $workOrder);

        if ($attachment->is_private) {
            $this->authorize('viewPrivateAttachments', $workOrder);
        }

        return $this->attachments->stream($workOrder, $attachment, $request->boolean('inline'));
    }

    public function destroyAttachment(WorkOrder $workOrder, WorkOrderAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $workOrder);

        if ($attachment->is_private) {
            $this->authorize('viewPrivateAttachments', $workOrder);
        }

        $this->attachments->delete($workOrder, $attachment);

        return redirect()
            ->route('work-orders.edit', ['work_order' => $workOrder, 'tab' => 'attachments'])
            ->with('success', 'work_order_attachment_deleted_successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Company $owner, WorkOrderStage $stage, ?WorkOrder $workOrder = null): array
    {
        $includeUserIds = [];
        $includeTechnicianIds = [];
        $includeArticleIds = $this->lineArticleIds($workOrder);

        if ($workOrder !== null) {
            $workOrder->loadMissing(['establishment:id,company_id', 'technicians']);
            $includeUserIds = $workOrder->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

            if ($workOrder->responsible_user_id !== null) {
                $includeUserIds[] = (int) $workOrder->responsible_user_id;
            }

            $includeTechnicianIds = $workOrder->technicians
                ->pluck('company_relationship_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return [
            'statusOptions' => $this->workOrders->statusOptions(
                $stage,
                $workOrder?->status_id !== null ? (int) $workOrder->status_id : null,
            ),
            'typeOptions' => $this->workOrders->typeOptions(),
            'priorityOptions' => $this->workOrders->priorityOptions(
                $workOrder?->establishment?->company_id !== null
                    ? (int) $workOrder->establishment->company_id
                    : null,
                $workOrder?->client_priority_id !== null ? [(int) $workOrder->client_priority_id] : [],
            ),
            'userOptions' => $this->workOrders->userOptions($owner, $includeUserIds),
            'establishmentOptions' => $this->workOrders->establishmentOptions(
                $owner,
                $workOrder?->establishment_id !== null ? [(int) $workOrder->establishment_id] : [],
            ),
            'contractOptions' => $this->workOrders->contractOptions(
                $owner,
                $workOrder?->contract_id !== null ? [(int) $workOrder->contract_id] : [],
            ),
            'requesterOptions' => $this->workOrders->requesterOptions(
                $owner,
                $workOrder?->establishment_id,
                $workOrder?->requester_id !== null ? [(int) $workOrder->requester_id] : [],
            ),
            'technicianOptions' => $this->workOrders->technicianOptions($owner, $includeTechnicianIds),
            'articleOptions' => $this->workOrders->articleOptions(
                $owner,
                null,
                null,
                null,
                $includeArticleIds,
            ),
            'technicianStatusOptions' => $this->workOrders->technicianStatusOptions(),
            'attendanceTypeOptions' => $this->workOrders->attendanceTypeOptions(),
            'checklistItems' => $workOrder !== null
                ? $this->workOrders->checklistItemsForWorkOrder($workOrder)
                : [],
        ];
    }

    /**
     * @return list<int>
     */
    private function lineArticleIds(?WorkOrder $workOrder): array
    {
        if ($workOrder === null) {
            return [];
        }

        $workOrder->loadMissing('lines');

        return $workOrder->lines
            ->pluck('article_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
