<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Config;

use App\Domain\Config\Provinces\Services\ProvinceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Config\Provinces\SyncCountryProvincesRequest;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

final class ProvinceController extends Controller
{
    public function __construct(
        private readonly ProvinceService $provinces,
    ) {}

    public function forCountry(Country $country): JsonResponse
    {
        $this->authorize('viewAny', Province::class);

        return response()->json([
            'data' => $this->provinces->forCountry($country),
        ]);
    }

    public function syncForCountry(SyncCountryProvincesRequest $request, Country $country): JsonResponse
    {
        $rows = $request->validated('provinces');

        return response()->json([
            'data' => $this->provinces->syncForCountry($country, $rows),
        ]);
    }
}
