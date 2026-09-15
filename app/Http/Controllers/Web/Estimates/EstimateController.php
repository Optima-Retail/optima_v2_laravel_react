<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Estimates;

use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\TechnicianSearchService;
use App\Domain\WorkOrders\Services\WorkOrderAttachmentService;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\AuthorizesEstimates;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Estimates\BulkUpdateEstimateStatusRequest;
use App\Http\Requests\Web\Estimates\SearchEstimateClientRatesRequest;
use App\Http\Requests\Web\Estimates\SearchEstimateTechniciansRequest;
use App\Http\Requests\Web\Estimates\StoreEstimateAttachmentRequest;
use App\Http\Requests\Web\Estimates\StoreEstimateRequest;
use App\Http\Requests\Web\Estimates\UpdateEstimateRequest;
use App\Models\Company;
use App\Models\NumberingPattern;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Policies\EstimatePolicy;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EstimateController extends Controller
{
    use AuthorizesEstimates;
    use ResolvesActiveCompany;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderAttachmentService $attachments,
        private readonly NumberingPatternService $numberingPatterns,
        private readonly DocumentChatService $chats,
        private readonly TechnicianSearchService $technicianSearch,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeEstimate('viewAny');

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

        $user = $request->user();

        return Inertia::render('Estimates/Index', [
            'filters' => $filters,
            'statusOptions' => $this->workOrders->statusOptions(WorkOrderStage::Estimate),
            'can' => [
                'create' => $user !== null && app(EstimatePolicy::class)->create($user),
                'update' => $user?->can('estimates.update') ?? false,
                'delete' => $user?->can('estimates.delete') ?? false,
                'configure_pattern' => ($user?->can('create', NumberingPattern::class) ?? false)
                    || ($user?->can('numbering_patterns.update') ?? false),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeEstimate('viewAny');

        $owner = $this->activeCompany($request);
        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'subject', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'pending', 'created_from', 'created_to', 'establishment_id'],
        );
        $filters['stage'] = WorkOrderStage::Estimate->value;

        $scopedToEstablishment = (int) ($filters['establishment_id'] ?? 0) > 0;

        if (! $scopedToEstablishment && ! $request->has('pending') && ($filters['pending'] ?? '') === '') {
            $filters['pending'] = '1';
        }

        return TabulatorResponse::fromPaginator(
            $this->workOrders->paginateForWeb($owner, $filters),
        );
    }

    public function totals(Request $request): JsonResponse
    {
        $this->authorizeEstimate('viewAny');

        $owner = $this->activeCompany($request);
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'pending' => $request->has('pending')
                ? $request->string('pending')->trim()->toString()
                : '1',
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'establishment_id' => $request->integer('establishment_id') ?: null,
            'stage' => WorkOrderStage::Estimate->value,
        ];

        if (! $request->has('pending') && ($filters['pending'] ?? '') === '') {
            $filters['pending'] = '1';
        }

        return response()->json($this->workOrders->totalsForOwner($owner, $filters));
    }

    public function bulkStatus(BulkUpdateEstimateStatusRequest $request): JsonResponse
    {
        $owner = $this->activeCompany($request);
        $validated = $request->validated();

        $result = $this->workOrders->bulkChangeStatus(
            $owner,
            array_map('intval', $validated['ids']),
            (int) $validated['status_id'],
            WorkOrderStage::Estimate,
            $request->user(),
            isset($validated['status_justification']) ? (string) $validated['status_justification'] : null,
        );

        return response()->json($result);
    }

    public function searchTechnicians(SearchEstimateTechniciansRequest $request): JsonResponse
    {
        $owner = $this->activeCompany($request);

        return response()->json(
            $this->technicianSearch->search($owner, $request->validated()),
        );
    }

    public function clientRates(SearchEstimateClientRatesRequest $request): JsonResponse
    {
        $owner = $this->activeCompany($request);
        $validated = $request->validated();

        return response()->json(
            $this->workOrders->clientRatesForEstimate(
                $owner,
                (int) $validated['establishment_id'],
                (int) $validated['work_order_type_id'],
            ),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorizeEstimate('create');

        $owner = $this->activeCompany($request);
        $stage = WorkOrderStage::Estimate;
        $suggestedCode = $this->numberingPatterns->peekNext($owner, NumberingResource::Estimates->value);

        $defaults = $this->workOrders->defaultsFromContract(
            $owner,
            $request->integer('contract_id') ?: null,
            $request->integer('establishment_id') ?: null,
        );

        return Inertia::render('Estimates/Create', [
            'suggestedCode' => $suggestedCode,
            'codeIsAutomatic' => $suggestedCode !== null,
            'defaultStatusId' => $this->workOrders->defaultStatusId($stage),
            'defaultEstablishmentId' => $defaults['establishment_id'],
            'defaultContractId' => $defaults['contract_id'],
            'defaultSubject' => $defaults['subject'],
            ...$this->formOptions($owner, $stage),
        ]);
    }

    public function store(StoreEstimateRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $estimate = $this->workOrders->create($owner, $request->validated());

        if ($estimate->isConfirmedWorkOrder()) {
            return redirect()
                ->route('work-orders.edit', $estimate)
                ->with('success', 'work_order_confirmed_successfully');
        }

        return redirect()
            ->route('estimates.edit', $estimate)
            ->with('success', 'estimate_created_successfully');
    }

    public function edit(Request $request, WorkOrder $estimate): Response
    {
        $this->authorizeEstimate('update', $estimate);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $policy = app(EstimatePolicy::class);
        $estimate->loadMissing('status');
        $canViewAttachments = $user !== null && $policy->viewAttachments($user, $estimate);
        $canViewPrivateAttachments = $user !== null && $policy->viewPrivateAttachments($user, $estimate);
        $canUpdateClosed = $user !== null && $policy->updateClosed($user, $estimate);
        $isOpen = (bool) ($estimate->status?->is_open ?? true);
        $stage = WorkOrderStage::Estimate;

        return Inertia::render('Estimates/Edit', [
            'estimate' => $this->workOrders->toFormData($estimate),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForWorkOrder($estimate, $canViewPrivateAttachments)
                : [],
            ...$this->formOptions($owner, $stage, $estimate),
            'fields_locked' => ! $isOpen && ! $canUpdateClosed,
            'chat' => $user !== null
                ? $this->chats->payload(ChatDocumentType::WorkOrder, (int) $estimate->id, $user)
                : null,
            'can' => [
                'delete' => $user !== null && $policy->delete($user, $estimate),
                'update_closed' => $canUpdateClosed,
                'view_attachments' => $canViewAttachments,
                'view_private_attachments' => $canViewPrivateAttachments,
                'upload_attachments' => $user !== null && $policy->uploadAttachments($user, $estimate),
                'download_attachments' => $user !== null && $policy->downloadAttachments($user, $estimate),
                'delete_attachments' => $user !== null && $policy->deleteAttachments($user, $estimate),
                'post_chat' => $user !== null && $policy->update($user, $estimate),
            ],
        ]);
    }

    public function update(UpdateEstimateRequest $request, WorkOrder $estimate): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $result = $this->workOrders->update($owner, $estimate, $request->validated(), $request->user());
        $fresh = $result['work_order'];

        if ($fresh->isConfirmedWorkOrder()) {
            return redirect()
                ->route('work-orders.edit', $fresh)
                ->with('success', 'work_order_confirmed_successfully');
        }

        return redirect()
            ->route('estimates.edit', $fresh)
            ->with('success', 'estimate_updated_successfully');
    }

    public function destroy(WorkOrder $estimate): RedirectResponse
    {
        $this->authorizeEstimate('delete', $estimate);

        $this->workOrders->delete($estimate);

        return redirect()
            ->route('estimates.index')
            ->with('success', 'estimate_deleted_successfully');
    }

    public function storeAttachment(StoreEstimateAttachmentRequest $request, WorkOrder $estimate): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->attachments->store($estimate, $user, $file, $request->boolean('is_private'));

        return redirect()
            ->route('estimates.edit', ['estimate' => $estimate, 'tab' => 'attachments'])
            ->with('success', 'estimate_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Request $request, WorkOrder $estimate, WorkOrderAttachment $attachment): StreamedResponse
    {
        $this->authorizeEstimate('downloadAttachments', $estimate);

        if ($attachment->is_private) {
            $this->authorizeEstimate('viewPrivateAttachments', $estimate);
        }

        return $this->attachments->stream($estimate, $attachment, $request->boolean('inline'));
    }

    public function destroyAttachment(Request $request, WorkOrder $estimate, WorkOrderAttachment $attachment): RedirectResponse
    {
        $this->authorizeEstimate('deleteAttachments', $estimate);

        if ($attachment->is_private) {
            $this->authorizeEstimate('viewPrivateAttachments', $estimate);
        }

        $this->attachments->delete($estimate, $attachment);

        return redirect()
            ->route('estimates.edit', ['estimate' => $estimate, 'tab' => 'attachments'])
            ->with('success', 'estimate_attachment_deleted_successfully');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Company $owner, WorkOrderStage $stage, ?WorkOrder $workOrder = null): array
    {
        $includeUserIds = [];

        if ($workOrder !== null) {
            $includeUserIds = $workOrder->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

            if ($workOrder->responsible_user_id !== null) {
                $includeUserIds[] = (int) $workOrder->responsible_user_id;
            }
        }

        return [
            'statusOptions' => $this->workOrders->statusOptions(
                $stage,
                $workOrder?->status_id !== null ? (int) $workOrder->status_id : null,
            ),
            'typeOptions' => $this->workOrders->typeOptions(),
            'priorityOptions' => $this->workOrders->priorityOptions(),
            'userOptions' => $this->workOrders->userOptions($owner, $includeUserIds),
            'establishmentOptions' => $this->workOrders->establishmentOptions(
                $owner,
                $workOrder?->establishment_id !== null ? [(int) $workOrder->establishment_id] : [],
            ),
            'contractOptions' => $this->workOrders->contractOptions(
                $owner,
                $workOrder?->contract_id !== null ? [(int) $workOrder->contract_id] : [],
            ),
            'requesterOptions' => $this->workOrders->requesterOptions($owner, $workOrder?->establishment_id),
            'technicianOptions' => $this->workOrders->technicianOptions($owner),
            'articleOptions' => $this->workOrders->articleOptions(
                $owner,
                $workOrder?->establishment_id !== null ? (int) $workOrder->establishment_id : null,
                $workOrder?->client_priority_id !== null ? (int) $workOrder->client_priority_id : null,
                $workOrder?->work_order_type_id !== null ? (int) $workOrder->work_order_type_id : null,
                $this->lineArticleIds($workOrder),
            ),
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
