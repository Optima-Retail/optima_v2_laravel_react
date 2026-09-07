<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Domain\Companies\Services\CompanyRelationshipService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Requests\Web\Companies\StoreCompanyRelationshipRequest;
use App\Http\Requests\Web\Companies\UpdateCompanyRelationshipRequest;
use App\Http\Resources\Api\V3\CompanyRelationshipResource;
use App\Models\CompanyRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CompanyRelationshipController extends BaseApiController
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly CompanyRelationshipService $relationships,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CompanyRelationship::class);

        $relationships = $this->relationships->paginateForOwner(
            $this->activeCompany($request),
            filters: [
                'search' => $request->string('search')->trim()->toString(),
                'kind' => $request->string('kind')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString(),
                'direction' => $request->string('direction')->trim()->toString(),
            ],
            perPage: $request->integer('per_page', 15),
        );

        return $this->success(CompanyRelationshipResource::collection($relationships));
    }

    public function store(StoreCompanyRelationshipRequest $request): JsonResponse
    {
        $relationship = $this->relationships->create(
            $this->activeCompany($request),
            $request->validated(),
            $request->user(),
        );

        return $this->created(
            new CompanyRelationshipResource($relationship),
            'company_relationship_created_successfully',
        );
    }

    public function show(CompanyRelationship $relationship): JsonResponse
    {
        $this->authorize('view', $relationship);

        return $this->success(new CompanyRelationshipResource($relationship->load('relatedCompany')));
    }

    public function update(UpdateCompanyRelationshipRequest $request, CompanyRelationship $relationship): JsonResponse
    {
        $relationship = $this->relationships->update($relationship, $request->validated(), $request->user());

        return $this->success(
            new CompanyRelationshipResource($relationship),
            'company_relationship_updated_successfully',
        );
    }

    public function destroy(CompanyRelationship $relationship): JsonResponse
    {
        $this->authorize('delete', $relationship);

        $this->relationships->delete($relationship);

        return $this->success(null, 'company_relationship_deleted_successfully');
    }
}
