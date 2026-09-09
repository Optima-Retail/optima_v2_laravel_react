<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\IncidentSubtypes\Services\IncidentSubtypeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\IncidentSubtypes\SyncIncidentTypeSubtypesRequest;
use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use Illuminate\Http\JsonResponse;

final class IncidentSubtypeController extends Controller
{
    public function __construct(
        private readonly IncidentSubtypeService $subtypes,
    ) {}

    public function forType(IncidentType $incidentType): JsonResponse
    {
        $this->authorize('viewAny', IncidentSubtype::class);

        return response()->json([
            'data' => $this->subtypes->forType($incidentType),
        ]);
    }

    public function syncForType(SyncIncidentTypeSubtypesRequest $request, IncidentType $incidentType): JsonResponse
    {
        $rows = $request->validated('subtypes');

        return response()->json([
            'data' => $this->subtypes->syncForType($incidentType, $rows),
        ]);
    }
}
