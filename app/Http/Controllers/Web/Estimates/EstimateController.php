<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Estimates;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderAttachmentService;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\AuthorizesEstimates;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
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
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        $user = $request->user();

        return Inertia::render('Estimates/Index', [
            'filters' => $filters,
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
            filterKeys: ['search', 'pending', 'created_from', 'created_to'],
        );
        $filters['stage'] = WorkOrderStage::Estimate->value;

        if (! $request->has('pending') && ($filters['pending'] ?? '') === '') {
            $filters['pending'] = '1';
        }

        return TabulatorResponse::fromPaginator(
            $this->workOrders->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorizeEstimate('create');

        $owner = $this->activeCompany($request);
        $stage = WorkOrderStage::Estimate;
        $suggestedCode = $this->numberingPatterns->peekNext($owner, NumberingResource::Estimates->value);

        return Inertia::render('Estimates/Create', [
            'suggestedCode' => $suggestedCode,
            'codeIsAutomatic' => $suggestedCode !== null,
            'defaultStatusId' => $this->workOrders->defaultStatusId($stage),
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
        $canViewAttachments = $user !== null && $policy->viewAttachments($user, $estimate);
        $stage = WorkOrderStage::Estimate;

        return Inertia::render('Estimates/Edit', [
            'estimate' => $this->workOrders->toFormData($estimate),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForWorkOrder($estimate)
                : [],
            ...$this->formOptions($owner, $stage, $estimate),
            'can' => [
                'delete' => $user !== null && $policy->delete($user, $estimate),
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user !== null && $policy->uploadAttachments($user, $estimate),
                'download_attachments' => $user !== null && $policy->downloadAttachments($user, $estimate),
                'delete_attachments' => $user !== null && $policy->deleteAttachments($user, $estimate),
            ],
        ]);
    }

    public function update(UpdateEstimateRequest $request, WorkOrder $estimate): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $result = $this->workOrders->update($owner, $estimate, $request->validated());
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
        $this->attachments->store($estimate, $user, $file);

        return redirect()
            ->route('estimates.edit', ['estimate' => $estimate, 'tab' => 'attachments'])
            ->with('success', 'estimate_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Request $request, WorkOrder $estimate, WorkOrderAttachment $attachment): StreamedResponse
    {
        $this->authorizeEstimate('downloadAttachments', $estimate);

        return $this->attachments->stream($estimate, $attachment);
    }

    public function destroyAttachment(Request $request, WorkOrder $estimate, WorkOrderAttachment $attachment): RedirectResponse
    {
        $this->authorizeEstimate('deleteAttachments', $estimate);

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
