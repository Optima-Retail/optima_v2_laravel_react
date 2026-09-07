<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Domain\Companies\Services\EstablishmentService;
use App\Http\Controllers\Concerns\ResolvesActiveCompany;
use App\Http\Requests\Web\Companies\StoreEstablishmentRequest;
use App\Http\Requests\Web\Companies\UpdateEstablishmentRequest;
use App\Http\Resources\Api\V3\EstablishmentResource;
use App\Models\Establishment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EstablishmentController extends BaseApiController
{
    use ResolvesActiveCompany;

    public function __construct(
        private readonly EstablishmentService $establishments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Establishment::class);

        $establishments = $this->establishments->paginateForOwner(
            $this->activeCompany($request),
            filters: [
                'search' => $request->string('search')->trim()->toString(),
                'sort' => $request->string('sort')->trim()->toString(),
                'direction' => $request->string('direction')->trim()->toString(),
            ],
            perPage: $request->integer('per_page', 15),
        );

        return $this->success(EstablishmentResource::collection($establishments));
    }

    public function store(StoreEstablishmentRequest $request): JsonResponse
    {
        $establishment = $this->establishments->create($request->validated());

        return $this->created(
            new EstablishmentResource($establishment),
            'establishment_created_successfully',
        );
    }

    public function show(Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        return $this->success(new EstablishmentResource($establishment));
    }

    public function update(UpdateEstablishmentRequest $request, Establishment $establishment): JsonResponse
    {
        $establishment = $this->establishments->update($establishment, $request->validated());

        return $this->success(
            new EstablishmentResource($establishment),
            'establishment_updated_successfully',
        );
    }

    public function destroy(Establishment $establishment): JsonResponse
    {
        $this->authorize('delete', $establishment);

        $this->establishments->delete($establishment);

        return $this->success(null, 'establishment_deleted_successfully');
    }
}
