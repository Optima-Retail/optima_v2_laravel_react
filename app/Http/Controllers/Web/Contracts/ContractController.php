<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Contracts;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Contracts\Services\ContractAttachmentService;
use App\Domain\Contracts\Services\ContractService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Contracts\StoreContractAttachmentRequest;
use App\Http\Requests\Web\Contracts\StoreContractRequest;
use App\Http\Requests\Web\Contracts\UpdateContractRequest;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractStatus;
use App\Models\NumberingPattern;
use App\Models\WorkOrder;
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

final class ContractController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly ContractService $contracts,
        private readonly ContractAttachmentService $attachments,
        private readonly NumberingPatternService $numberingPatterns,
        private readonly WorkOrderService $workOrders,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'contract_status_id' => $request->string('contract_status_id')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('Contracts/Index', [
            'filters' => $filters,
            'contractStatusOptions' => ContractStatus::query()
                ->orderBy('lifecycle')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ContractStatus $status): array => [
                    'id' => $status->id,
                    'label' => $status->name,
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => $request->user()?->can('create', Contract::class) ?? false,
                'update' => $request->user()?->can('contracts.update') ?? false,
                'delete' => $request->user()?->can('contracts.delete') ?? false,
                'configure_pattern' => ($request->user()?->can('create', NumberingPattern::class) ?? false)
                    || ($request->user()?->can('numbering_patterns.update') ?? false),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'code', 'description', 'signed_at', 'total_amount', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'contract_status_id', 'created_from', 'created_to'],
        );

        return TabulatorResponse::fromPaginator(
            $this->contracts->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Contract::class);

        $owner = $this->activeCompany($request);
        $clientIds = $this->contracts->accessibleCompanyIds($owner);
        $suggestedCode = $this->numberingPatterns->peekNext($owner, NumberingResource::Contracts->value);
        $codeIsAutomatic = $suggestedCode !== null;

        return Inertia::render('Contracts/Create', [
            'defaultCompanyId' => $clientIds[0] ?? null,
            'defaultContractStatusId' => $this->contracts->defaultContractStatusId(),
            'suggestedCode' => $suggestedCode,
            'codeIsAutomatic' => $codeIsAutomatic,
            'companyOptions' => $this->contracts->clientCompanyOptions(
                $owner,
                $clientIds[0] ?? null,
            ),
            'contractStatusOptions' => $this->contracts->contractStatusOptions(),
            'languageOptions' => $this->contracts->languageOptions(),
            'userOptions' => $this->contracts->userOptions($owner),
            'establishmentOptions' => $this->contracts->establishmentOptions($owner),
            'workOrderTypeOptions' => $this->contracts->workOrderTypeOptions(),
            'formTemplateOptions' => $this->contracts->formTemplateOptions($owner),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $record = $this->contracts->create($owner, $request->validated());

        return redirect()
            ->route('contracts.edit', $record)
            ->with('success', 'contract_created_successfully');
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $this->authorize('update', $contract);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $canViewAttachments = $user?->can('viewAttachments', $contract) ?? false;
        $canViewWorkOrders = $user?->can('viewAny', WorkOrder::class) ?? false;

        return Inertia::render('Contracts/Edit', [
            'contract' => $this->contracts->toFormData($contract),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForContract($contract)
                : [],
            'workOrderTotals' => $canViewWorkOrders
                ? $this->workOrders->totalsForContract($owner, (int) $contract->id, WorkOrderStage::WorkOrder)
                : null,
            'companyOptions' => $this->contracts->clientCompanyOptions(
                $owner,
                $contract->company_id !== null ? (int) $contract->company_id : null,
            ),
            'contractStatusOptions' => $this->contracts->contractStatusOptions(
                $contract->contract_status_id !== null ? (int) $contract->contract_status_id : null,
            ),
            'languageOptions' => $this->contracts->languageOptions(),
            'userOptions' => $this->contracts->userOptions($owner),
            'establishmentOptions' => $this->contracts->establishmentOptions(
                $owner,
                $contract->establishments->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ),
            'workOrderTypeOptions' => $this->contracts->workOrderTypeOptions(),
            'formTemplateOptions' => $this->contracts->formTemplateOptions($owner),
            'can' => [
                'delete' => $user?->can('delete', $contract) ?? false,
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $contract) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $contract) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $contract) ?? false,
                'view_work_orders' => $canViewWorkOrders,
                'create_work_orders' => $user?->can('create', WorkOrder::class) ?? false,
                'update_work_orders' => $user?->can('work_orders.update') ?? false,
                'delete_work_orders' => $user?->can('work_orders.delete') ?? false,
            ],
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->contracts->update($contract, $request->validated());

        $tab = $request->string('tab')->trim()->toString();

        return redirect()
            ->route('contracts.edit', array_filter([
                'contract' => $contract,
                'tab' => $tab !== '' && $tab !== 'details' ? $tab : null,
            ]))
            ->with('success', 'contract_updated_successfully');
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $this->contracts->delete($contract);

        return redirect()
            ->route('contracts.index')
            ->with('success', 'contract_deleted_successfully');
    }

    public function storeAttachment(StoreContractAttachmentRequest $request, Contract $contract): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->attachments->store($contract, $user, $file);

        return redirect()
            ->route('contracts.edit', ['contract' => $contract, 'tab' => 'attachments'])
            ->with('success', 'contract_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Contract $contract, ContractAttachment $attachment, Request $request): StreamedResponse
    {
        $this->authorize('downloadAttachments', $contract);

        return $this->attachments->stream($contract, $attachment, $request->boolean('inline'));
    }

    public function destroyAttachment(Contract $contract, ContractAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $contract);

        $this->attachments->delete($contract, $attachment);

        return redirect()
            ->route('contracts.edit', ['contract' => $contract, 'tab' => 'attachments'])
            ->with('success', 'contract_attachment_deleted_successfully');
    }
}
