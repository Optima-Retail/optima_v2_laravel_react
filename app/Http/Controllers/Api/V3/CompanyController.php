<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Domain\Companies\Services\CompanyService;
use App\Http\Requests\Web\Companies\StoreCompanyRequest;
use App\Http\Requests\Web\Companies\UpdateCompanyRequest;
use App\Http\Resources\Api\V3\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompanyController extends BaseApiController
{
    public function __construct(
        private readonly CompanyService $companies,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companies->paginate(
            filters: [
                'search' => $request->string('search')->trim()->toString(),
                'kind' => $request->string('kind')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString(),
                'direction' => $request->string('direction')->trim()->toString(),
            ],
            perPage: $request->integer('per_page', 15),
            member: $request->user(),
        );

        return $this->success(CompanyResource::collection($companies));
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->companies->create($request->validated(), $request->user());

        return $this->created(
            new CompanyResource($company),
            'company_created_successfully',
        );
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return $this->success(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company = $this->companies->update($company, $request->validated());

        return $this->success(
            new CompanyResource($company),
            'company_updated_successfully',
        );
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->companies->delete($company);

        return $this->success(null, 'company_deleted_successfully');
    }
}
