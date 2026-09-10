<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Contracts;

use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\Config\NumberingPatterns\Services\NumberingPatternService;
use App\Domain\Contracts\Services\ContractAttachmentService;
use App\Domain\Contracts\Services\ContractService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Contracts\StoreContractAttachmentRequest;
use App\Http\Requests\Web\Contracts\StoreContractRequest;
use App\Http\Requests\Web\Contracts\UpdateContractRequest;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\NumberingPattern;
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
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 12),
            ]),
        ];

        return Inertia::render('Contracts/Index', [
            'filters' => $filters,
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
            filterKeys: ['search'],
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
            'companyOptions' => $this->contracts->clientCompanyOptions($owner),
            'contractStatusOptions' => $this->contracts->contractStatusOptions(),
            'languageOptions' => $this->contracts->languageOptions(),
            'userOptions' => $this->contracts->userOptions(),
            'establishmentOptions' => $this->contracts->establishmentOptions($owner),
        ]);
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $owner = $this->activeCompany($request);
        $this->contracts->create($owner, $request->validated());

        return redirect()
            ->route('contracts.index')
            ->with('success', 'contract_created_successfully');
    }

    public function edit(Request $request, Contract $contract): Response
    {
        $this->authorize('update', $contract);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $canViewAttachments = $user?->can('viewAttachments', $contract) ?? false;

        return Inertia::render('Contracts/Edit', [
            'contract' => $this->contracts->toFormData($contract),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForContract($contract)
                : [],
            'companyOptions' => $this->contracts->clientCompanyOptions($owner),
            'contractStatusOptions' => $this->contracts->contractStatusOptions(
                $contract->contract_status_id !== null ? (int) $contract->contract_status_id : null,
            ),
            'languageOptions' => $this->contracts->languageOptions(),
            'userOptions' => $this->contracts->userOptions(),
            'establishmentOptions' => $this->contracts->establishmentOptions($owner),
            'can' => [
                'delete' => $user?->can('delete', $contract) ?? false,
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $contract) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $contract) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $contract) ?? false,
            ],
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): RedirectResponse
    {
        $this->contracts->update($contract, $request->validated());

        return redirect()
            ->route('contracts.index')
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

    public function downloadAttachment(Contract $contract, ContractAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachments', $contract);

        return $this->attachments->stream($contract, $attachment);
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
