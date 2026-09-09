<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Companies;

use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Services\CompanyRelationshipService;
use App\Domain\Companies\Services\CompanyService;
use App\Domain\Companies\Services\EstablishmentService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Companies\StoreCompanyRelationshipRequest;
use App\Http\Requests\Web\Companies\UpdateCompanyRelationshipRequest;
use App\Models\CompanyRelationship;
use App\Support\ListQuery;
use App\Support\TabulatorQuery;
use App\Support\TabulatorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyRelationshipController extends Controller
{
    use ResolvesActiveCompany;

    /** @var list<string> */
    private const CLIENT_KINDS = [CompanyRelationshipKind::Customer->value];

    /** @var list<string> */
    private const SUPPLIER_KINDS = [
        CompanyRelationshipKind::Supplier->value,
        CompanyRelationshipKind::Technician->value,
    ];

    public function __construct(
        private readonly CompanyRelationshipService $relationships,
        private readonly CompanyService $companies,
        private readonly EstablishmentService $establishments,
    ) {}

    public function indexClients(Request $request): Response
    {
        return $this->indexScoped($request, 'Clients/Index', self::CLIENT_KINDS);
    }

    public function indexSuppliers(Request $request): Response
    {
        return $this->indexScoped($request, 'Suppliers/Index', self::SUPPLIER_KINDS);
    }

    public function dataClients(Request $request): JsonResponse
    {
        return $this->dataScoped($request, self::CLIENT_KINDS);
    }

    public function dataSuppliers(Request $request): JsonResponse
    {
        return $this->dataScoped($request, self::SUPPLIER_KINDS);
    }

    public function createClient(Request $request): Response
    {
        $this->authorize('create', CompanyRelationship::class);

        $owner = $this->activeCompany($request);

        return Inertia::render('Clients/Create', [
            'companyOptions' => $this->companies->companyOptions($owner->id),
            'formOptions' => $this->relationships->formOptions(),
        ]);
    }

    public function createSupplier(Request $request): Response
    {
        $this->authorize('create', CompanyRelationship::class);

        $owner = $this->activeCompany($request);

        return Inertia::render('Suppliers/Create', [
            'companyOptions' => $this->companies->companyOptions($owner->id),
            'formOptions' => $this->relationships->formOptions(),
        ]);
    }

    public function storeClient(StoreCompanyRelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['kind'] = CompanyRelationshipKind::Customer->value;

        $this->relationships->create(
            $this->activeCompany($request),
            $data,
            $request->user(),
        );

        return redirect()
            ->route('clients.index')
            ->with('success', 'client_created_successfully');
    }

    public function storeSupplier(StoreCompanyRelationshipRequest $request): RedirectResponse
    {
        $data = $request->validated();
        abort_unless(in_array($data['kind'], self::SUPPLIER_KINDS, true), 422);

        $this->relationships->create(
            $this->activeCompany($request),
            $data,
            $request->user(),
        );

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'supplier_created_successfully');
    }

    public function editClient(Request $request, CompanyRelationship $relationship): Response
    {
        $this->assertKind($relationship, self::CLIENT_KINDS);
        $this->authorize('update', $relationship);

        $owner = $this->activeCompany($request);

        return Inertia::render('Clients/Edit', [
            'relationship' => $this->relationships->toFormData($relationship),
            'companyOptions' => $this->companies->companyOptions($owner->id),
            'formOptions' => $this->relationships->formOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $relationship) ?? false,
            ],
        ]);
    }

    public function editSupplier(Request $request, CompanyRelationship $relationship): Response
    {
        $this->assertKind($relationship, self::SUPPLIER_KINDS);
        $this->authorize('update', $relationship);

        $owner = $this->activeCompany($request);

        return Inertia::render('Suppliers/Edit', [
            'relationship' => $this->relationships->toFormData($relationship),
            'companyOptions' => $this->companies->companyOptions($owner->id),
            'formOptions' => $this->relationships->formOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $relationship) ?? false,
            ],
        ]);
    }

    public function updateClient(UpdateCompanyRelationshipRequest $request, CompanyRelationship $relationship): RedirectResponse
    {
        $this->assertKind($relationship, self::CLIENT_KINDS);

        $data = $request->validated();
        $data['kind'] = CompanyRelationshipKind::Customer->value;

        $this->relationships->update($relationship, $data, $request->user());

        return redirect()
            ->route('clients.index')
            ->with('success', 'client_updated_successfully');
    }

    public function updateSupplier(UpdateCompanyRelationshipRequest $request, CompanyRelationship $relationship): RedirectResponse
    {
        $this->assertKind($relationship, self::SUPPLIER_KINDS);

        $data = $request->validated();
        abort_unless(in_array($data['kind'], self::SUPPLIER_KINDS, true), 422);

        $this->relationships->update($relationship, $data, $request->user());

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'supplier_updated_successfully');
    }

    public function destroyClient(CompanyRelationship $relationship): RedirectResponse
    {
        $this->assertKind($relationship, self::CLIENT_KINDS);
        $this->authorize('delete', $relationship);

        $this->relationships->delete($relationship);

        return redirect()
            ->route('clients.index')
            ->with('success', 'client_deleted_successfully');
    }

    public function destroySupplier(CompanyRelationship $relationship): RedirectResponse
    {
        $this->assertKind($relationship, self::SUPPLIER_KINDS);
        $this->authorize('delete', $relationship);

        $this->relationships->delete($relationship);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'supplier_deleted_successfully');
    }

    public function establishments(CompanyRelationship $relationship): JsonResponse
    {
        $this->assertKind($relationship, self::CLIENT_KINDS);
        $this->authorize('view', $relationship);

        abort_if($relationship->related_company_id === null, 404);

        return response()->json([
            'data' => $this->establishments->forClientCompany((int) $relationship->related_company_id),
        ]);
    }

    /**
     * @param  list<string>  $kinds
     */
    private function indexScoped(Request $request, string $component, array $kinds): Response
    {
        $this->authorize('viewAny', CompanyRelationship::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'kind' => $this->validKindFilter($request, $kinds),
            'sort' => $request->string('sort')->trim()->toString() ?: 'id',
            'direction' => $request->string('direction')->trim()->toString() ?: 'desc',
            'per_page' => $request->integer('per_page', 12),
        ];

        return Inertia::render($component, [
            'filters' => [
                ...$filters,
                'per_page' => (string) ListQuery::perPage($filters),
            ],
            'can' => [
                'create' => $request->user()?->can('create', CompanyRelationship::class) ?? false,
            ],
        ]);
    }

    /**
     * @param  list<string>  $kinds
     */
    private function dataScoped(Request $request, array $kinds): JsonResponse
    {
        $this->authorize('viewAny', CompanyRelationship::class);

        $owner = $this->activeCompany($request);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'kind', 'status', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
            filterKeys: ['search', 'kind'],
        );

        $filters['kind'] = $this->validKindFilter($request, $kinds, $filters['kind']);
        $filters['kinds'] = $kinds;

        return TabulatorResponse::fromPaginator(
            $this->relationships->paginateForWeb($owner, $filters),
        );
    }

    /**
     * @param  list<string>  $kinds
     */
    private function validKindFilter(Request $request, array $kinds, ?string $value = null): string
    {
        $kindFilter = $value ?? $request->string('kind')->trim()->toString();

        return in_array($kindFilter, $kinds, true) ? $kindFilter : '';
    }

    /**
     * @param  list<string>  $kinds
     */
    private function assertKind(CompanyRelationship $relationship, array $kinds): void
    {
        abort_unless(in_array($relationship->kind->value, $kinds, true), 404);
    }
}
