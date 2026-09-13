<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\WorkOrders;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderAttachmentService;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
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
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('WorkOrders/Index', [
            'filters' => $filters,
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
            filterKeys: ['search', 'pending', 'created_from', 'created_to'],
        );
        $filters['stage'] = WorkOrderStage::WorkOrder->value;

        if (! $request->has('pending') && ($filters['pending'] ?? '') === '') {
            $filters['pending'] = '1';
        }

        return TabulatorResponse::fromPaginator(
            $this->workOrders->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WorkOrder::class);

        $owner = $this->activeCompany($request);
        $stage = WorkOrderStage::WorkOrder;
        $suggestedCode = $this->numberingPatterns->peekNext($owner, NumberingResource::WorkOrders->value);

        return Inertia::render('WorkOrders/Create', [
            'suggestedCode' => $suggestedCode,
            'codeIsAutomatic' => $suggestedCode !== null,
            'defaultStatusId' => $this->workOrders->defaultStatusId($stage),
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
        $canViewAttachments = $user?->can('viewAttachments', $workOrder) ?? false;
        $stage = WorkOrderStage::WorkOrder;

        return Inertia::render('WorkOrders/Edit', [
            'workOrder' => $this->workOrders->toFormData($workOrder),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForWorkOrder($workOrder)
                : [],
            ...$this->formOptions($owner, $stage, $workOrder),
            'can' => [
                'delete' => $user?->can('delete', $workOrder) ?? false,
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $workOrder) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $workOrder) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $workOrder) ?? false,
            ],
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $result = $this->workOrders->update($owner, $workOrder, $request->validated());
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
        $this->attachments->store($workOrder, $user, $file);

        return redirect()
            ->route('work-orders.edit', ['work_order' => $workOrder, 'tab' => 'attachments'])
            ->with('success', 'work_order_attachment_uploaded_successfully');
    }

    public function downloadAttachment(WorkOrder $workOrder, WorkOrderAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachments', $workOrder);

        return $this->attachments->stream($workOrder, $attachment);
    }

    public function destroyAttachment(WorkOrder $workOrder, WorkOrderAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $workOrder);

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

        if ($workOrder !== null) {
            $includeUserIds = $workOrder->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

            if ($workOrder->responsible_user_id !== null) {
                $includeUserIds[] = (int) $workOrder->responsible_user_id;
            }
        }

        return [
            'statusOptions' => $this->workOrders->statusOptions($stage),
            'typeOptions' => $this->workOrders->typeOptions(),
            'priorityOptions' => $this->workOrders->priorityOptions(),
            'userOptions' => $this->workOrders->userOptions($owner, $includeUserIds),
            'establishmentOptions' => $this->workOrders->establishmentOptions($owner),
            'requesterOptions' => $this->workOrders->requesterOptions($owner, $workOrder?->establishment_id),
            'technicianOptions' => $this->workOrders->technicianOptions($owner),
            'articleOptions' => $this->workOrders->articleOptions(),
        ];
    }
}
