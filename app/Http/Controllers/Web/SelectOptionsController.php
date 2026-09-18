<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Companies\Services\CompanyService;
use App\Domain\Companies\Services\EstablishmentService;
use App\Domain\Companies\Support\CompanyMemberUsers;
use App\Domain\Compliments\Services\ComplimentService;
use App\Domain\Config\Brands\Services\BrandService;
use App\Domain\Contracts\Services\ContractService;
use App\Domain\Incidents\Services\IncidentService;
use App\Domain\TechnicianRequests\Services\TechnicianRequestService;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\Contract;
use App\Models\Delegation;
use App\Models\Establishment;
use App\Models\Evaluation;
use App\Models\Incident;
use App\Models\TechnicianIncident;
use App\Models\TechnicianRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SelectOptionsController extends Controller
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly BrandService $brands,
        private readonly CompanyService $companies,
        private readonly EstablishmentService $establishments,
        private readonly ContractService $contracts,
        private readonly WorkOrderService $workOrders,
        private readonly IncidentService $incidents,
        private readonly ComplimentService $compliments,
        private readonly TechnicianRequestService $technicianRequests,
    ) {}

    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $search = $request->string('search')->trim()->toString();
        $includeId = $request->filled('include_id') ? $request->integer('include_id') : null;
        $includeIds = $this->intList($request->input('include_ids', []));
        if ($includeId !== null) {
            $includeIds[] = $includeId;
        }
        $includeIds = array_values(array_unique(array_filter($includeIds, fn (int $id): bool => $id > 0)));
        $limit = max(1, min($request->integer('limit', 50), 100));
        $owner = $this->activeCompany($request);

        $options = match ($resource) {
            'brands' => $this->brandOptions($request, $search, $includeId, $limit),
            'users' => $this->userOptions($request, $owner, $search, $includeIds, $limit),
            'establishments' => $this->establishmentOptions($request, $owner, $search, $includeIds, $limit),
            'customers' => $this->customerOptions($request, $owner, $search, $includeIds, $limit),
            'technicians' => $this->technicianOptions($request, $owner, $search, $includeIds, $limit),
            'contracts' => $this->contractOptions($request, $owner, $search, $includeIds, $limit),
            'requesters' => $this->requesterOptions($request, $owner, $search, $includeIds, $limit),
            'articles' => $this->articleOptions($request, $owner, $search, $includeIds, $limit),
            'work-orders' => $this->workOrderOptions($request, $owner, $search, $includeIds, $limit),
            'evaluations' => $this->evaluationOptions($request, $owner, $search, $includeIds, $limit),
            default => abort(404),
        };

        return response()->json(['data' => $options]);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function brandOptions(Request $request, string $search, ?int $includeId, int $limit): array
    {
        $this->authorizeAny($request, [
            Brand::class,
            Company::class,
            CompanyRelationship::class,
            Compliment::class,
            Incident::class,
        ]);

        return $this->brands->searchOptions(
            search: $search !== '' ? $search : null,
            includeId: $includeId,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    private function userOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            User::class,
            Company::class,
            CompanyRelationship::class,
            Establishment::class,
            Contract::class,
            WorkOrder::class,
            Compliment::class,
            Incident::class,
            Brand::class,
            TechnicianRequest::class,
            TechnicianIncident::class,
            Evaluation::class,
        ]);

        $scope = $request->string('scope')->trim()->toString() ?: 'members';

        if ($scope === 'assignable') {
            $companyId = $request->filled('company_id') ? $request->integer('company_id') : $owner->id;
            $company = Company::query()->findOrFail($companyId);
            $this->authorize('update', $company);

            return $this->companies->searchAssignableUserOptions(
                $company,
                search: $search !== '' ? $search : null,
                includeUserIds: $includeIds,
                limit: $limit,
            );
        }

        return CompanyMemberUsers::searchOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeUserIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array<string, mixed>>
     */
    private function establishmentOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            Establishment::class,
            Contract::class,
            WorkOrder::class,
            Compliment::class,
            Incident::class,
            Evaluation::class,
        ]);

        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;

        if ($request->boolean('rich', true) && $request->user()?->can('viewAny', WorkOrder::class)) {
            return $this->workOrders->searchEstablishmentOptions(
                $owner,
                search: $search !== '' ? $search : null,
                includeIds: $includeIds,
                companyId: $companyId,
                limit: $limit,
            );
        }

        return $this->contracts->searchEstablishmentOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            companyId: $companyId,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    private function customerOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            Compliment::class,
            CompanyRelationship::class,
        ]);

        return $this->compliments->searchCustomerOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, logo_url: string|null}>
     */
    private function technicianOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            CompanyRelationship::class,
            Establishment::class,
            WorkOrder::class,
            TechnicianRequest::class,
            TechnicianIncident::class,
        ]);

        return $this->establishments->searchTechnicianOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    private function contractOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            Contract::class,
            WorkOrder::class,
        ]);

        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;

        return $this->workOrders->searchContractOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            companyId: $companyId,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, company_id: int}>
     */
    private function requesterOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [WorkOrder::class]);

        $establishmentId = $request->filled('establishment_id') ? $request->integer('establishment_id') : null;

        return $this->workOrders->searchRequesterOptions(
            $owner,
            search: $search !== '' ? $search : null,
            establishmentId: $establishmentId,
            includeIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string, code: string, description: string|null, unit_price: string}>
     */
    private function articleOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            Article::class,
            WorkOrder::class,
        ]);

        return $this->workOrders->articleOptions(
            $owner,
            establishmentId: $request->filled('establishment_id') ? $request->integer('establishment_id') : null,
            clientPriorityId: $request->filled('client_priority_id') ? $request->integer('client_priority_id') : null,
            workOrderTypeId: $request->filled('work_order_type_id') ? $request->integer('work_order_type_id') : null,
            includeArticleIds: $includeIds,
            search: $search !== '' ? $search : null,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    private function workOrderOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            WorkOrder::class,
            TechnicianRequest::class,
        ]);

        return $this->technicianRequests->searchWorkOrderOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<int>  $includeIds
     * @return list<array{id: int, label: string}>
     */
    private function evaluationOptions(Request $request, Company $owner, string $search, array $includeIds, int $limit): array
    {
        $this->authorizeAny($request, [
            Incident::class,
            Evaluation::class,
        ]);

        return $this->incidents->searchEvaluationOptions(
            $owner,
            search: $search !== '' ? $search : null,
            includeIds: $includeIds,
            limit: $limit,
        );
    }

    /**
     * @param  list<class-string>  $models
     */
    private function authorizeAny(Request $request, array $models): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        foreach ($models as $model) {
            if ($user->can('viewAny', $model)) {
                return;
            }
        }

        // Relationship create/update often needs brand/user picks without users.view.
        if (
            $user->can('viewAny', CompanyRelationship::class)
            || $user->can('create', CompanyRelationship::class)
            || $user->can('viewAny', Delegation::class)
        ) {
            return;
        }

        abort(403);
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $value): array
    {
        if (! is_array($value)) {
            $raw = trim((string) $value);

            if ($raw === '') {
                return [];
            }

            $value = preg_split('/\s*,\s*/', $raw) ?: [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($item): int => (int) $item, $value),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
