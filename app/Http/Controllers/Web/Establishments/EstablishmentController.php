<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Establishments;

use App\Domain\Companies\Services\CompanyService;
use App\Domain\Companies\Services\EstablishmentAttachmentService;
use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Config\Provinces\Services\ProvinceService;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Establishments\StoreEstablishmentAttachmentRequest;
use App\Http\Requests\Web\Establishments\StoreEstablishmentRequest;
use App\Http\Requests\Web\Establishments\UpdateEstablishmentRequest;
use App\Models\Establishment;
use App\Models\EstablishmentAttachment;
use App\Models\WorkOrder;
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

final class EstablishmentController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly EstablishmentService $establishments,
        private readonly EstablishmentAttachmentService $attachments,
        private readonly CompanyService $companies,
        private readonly ProvinceService $provinces,
        private readonly WorkOrderService $workOrders,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Establishment::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'is_active' => $request->string('is_active')->trim()->toString(),
            'created_from' => $request->string('created_from')->trim()->toString(),
            'created_to' => $request->string('created_to')->trim()->toString(),
            'sort' => $request->string('sort')->trim()->toString() ?: 'name',
            'direction' => $request->string('direction')->trim()->toString() ?: 'asc',
            'per_page' => (string) ListQuery::perPage([
                'per_page' => $request->integer('per_page', 25),
            ]),
        ];

        return Inertia::render('Establishments/Index', [
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Establishment::class) ?? false,
                'update' => $request->user()?->can('establishments.update') ?? false,
                'delete' => $request->user()?->can('establishments.delete') ?? false,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Establishment::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'code', 'city', 'is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc',
            filterKeys: ['search', 'is_active', 'created_from', 'created_to'],
        );

        return TabulatorResponse::fromPaginator(
            $this->establishments->paginateForWeb($owner, $filters),
        );
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Establishment::class);

        $owner = $this->activeCompany($request);
        $accessibleCompanyIds = $this->establishments->accessibleCompanyIds($owner);
        $requestedCompanyId = $request->integer('company_id') ?: null;
        $defaultCompanyId = $requestedCompanyId !== null && in_array($requestedCompanyId, $accessibleCompanyIds, true)
            ? $requestedCompanyId
            : ($accessibleCompanyIds[0] ?? null);

        return Inertia::render('Establishments/Create', [
            'defaultCompanyId' => $defaultCompanyId,
            'companyOptions' => $this->establishments->clientCompanyOptions($owner),
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'timezoneOptions' => $this->establishments->timezoneOptions(),
            'delegationOptions' => $this->establishments->delegationOptions(),
            'languageOptions' => $this->establishments->languageOptions(),
            'establishmentTypeOptions' => $this->establishments->establishmentTypeOptions(),
            'seriesOptions' => $this->establishments->seriesOptions(),
            'userOptions' => $this->establishments->userOptions($owner),
            'technicianOptions' => $this->establishments->technicianOptions($owner),
            'workOrderTypeOptions' => $this->establishments->workOrderTypeOptions(),
            'formTemplateOptions' => $this->establishments->formTemplateOptions($owner),
        ]);
    }

    public function store(StoreEstablishmentRequest $request): RedirectResponse
    {
        $record = $this->establishments->create($request->validated());

        return redirect()
            ->route('establishments.edit', $record)
            ->with('success', 'establishment_created_successfully');
    }

    public function edit(Request $request, Establishment $establishment): Response
    {
        $this->authorize('update', $establishment);

        $owner = $this->activeCompany($request);
        $user = $request->user();
        $canViewAttachments = $user?->can('viewAttachments', $establishment) ?? false;
        $canViewPrivate = $user?->can('viewPrivateAttachments', $establishment) ?? false;
        $canViewWorkOrders = $user?->can('viewAny', WorkOrder::class) ?? false;
        $canViewEstimates = $user !== null && app(EstimatePolicy::class)->viewAny($user);

        return Inertia::render('Establishments/Edit', [
            'establishment' => $this->establishments->toFormData($establishment),
            'attachments' => $canViewAttachments
                ? $this->attachments->listForEstablishment($establishment, $canViewPrivate)
                : [],
            'workOrderTotals' => $canViewWorkOrders
                ? $this->workOrders->totalsForEstablishment($owner, (int) $establishment->id, WorkOrderStage::WorkOrder)
                : null,
            'estimateTotals' => $canViewEstimates
                ? $this->workOrders->totalsForEstablishment($owner, (int) $establishment->id, WorkOrderStage::Estimate)
                : null,
            'companyOptions' => $this->establishments->clientCompanyOptions(
                $owner,
                $establishment->company_id !== null ? (int) $establishment->company_id : null,
            ),
            'countryOptions' => $this->companies->countryOptions(),
            'provinceOptions' => $this->provinces->options(),
            'timezoneOptions' => $this->establishments->timezoneOptions(),
            'delegationOptions' => $this->establishments->delegationOptions(),
            'languageOptions' => $this->establishments->languageOptions(),
            'establishmentTypeOptions' => $this->establishments->establishmentTypeOptions(),
            'seriesOptions' => $this->establishments->seriesOptions(
                $establishment->series_id !== null ? (int) $establishment->series_id : null,
            ),
            'userOptions' => $this->establishments->userOptions($owner),
            'technicianOptions' => $this->establishments->technicianOptions($owner),
            'workOrderTypeOptions' => $this->establishments->workOrderTypeOptions(),
            'formTemplateOptions' => $this->establishments->formTemplateOptions($owner),
            'can' => [
                'delete' => $user?->can('delete', $establishment) ?? false,
                'view_attachments' => $canViewAttachments,
                'upload_attachments' => $user?->can('uploadAttachments', $establishment) ?? false,
                'download_attachments' => $user?->can('downloadAttachments', $establishment) ?? false,
                'delete_attachments' => $user?->can('deleteAttachments', $establishment) ?? false,
                'view_private_attachments' => $canViewPrivate,
                'view_work_orders' => $canViewWorkOrders,
                'create_work_orders' => $user?->can('create', WorkOrder::class) ?? false,
                'update_work_orders' => $user?->can('work_orders.update') ?? false,
                'delete_work_orders' => $user?->can('work_orders.delete') ?? false,
                'view_estimates' => $canViewEstimates,
                'create_estimates' => $user !== null && app(EstimatePolicy::class)->create($user),
                'update_estimates' => $user?->can('estimates.update') ?? false,
                'delete_estimates' => $user?->can('estimates.delete') ?? false,
            ],
        ]);
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): RedirectResponse
    {
        $this->establishments->update($establishment, $request->validated());

        return redirect()
            ->route('establishments.edit', $establishment)
            ->with('success', 'establishment_updated_successfully');
    }

    public function destroy(Establishment $establishment): RedirectResponse
    {
        $this->authorize('delete', $establishment);

        $this->establishments->delete($establishment);

        return redirect()
            ->route('establishments.index')
            ->with('success', 'establishment_deleted_successfully');
    }

    public function storeAttachment(StoreEstablishmentAttachmentRequest $request, Establishment $establishment): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->attachments->store($establishment, $user, $file, $request->boolean('is_private'));

        return redirect()
            ->route('establishments.edit', ['establishment' => $establishment, 'tab' => 'attachments'])
            ->with('success', 'establishment_attachment_uploaded_successfully');
    }

    public function downloadAttachment(Request $request, Establishment $establishment, EstablishmentAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachments', $establishment);
        abort_unless($attachment->establishment_id === $establishment->id, 404);

        if ($attachment->is_private) {
            $this->authorize('viewPrivateAttachments', $establishment);
        }

        return $this->attachments->stream($establishment, $attachment, $request->boolean('inline'));
    }

    public function destroyAttachment(Establishment $establishment, EstablishmentAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $establishment);
        abort_unless($attachment->establishment_id === $establishment->id, 404);

        if ($attachment->is_private) {
            $this->authorize('viewPrivateAttachments', $establishment);
        }

        $this->attachments->delete($establishment, $attachment);

        return redirect()
            ->route('establishments.edit', ['establishment' => $establishment, 'tab' => 'attachments'])
            ->with('success', 'establishment_attachment_deleted_successfully');
    }
}
