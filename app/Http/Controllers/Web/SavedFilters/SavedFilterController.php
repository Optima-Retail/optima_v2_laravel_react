<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SavedFilters;

use App\Domain\SavedFilters\Enums\SavedFilterPageKey;
use App\Domain\SavedFilters\Services\SavedFilterService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SavedFilters\StoreSavedFilterRequest;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SavedFilterController extends Controller
{
    public function __construct(
        private readonly SavedFilterService $savedFilters,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $pageKey = $request->string('page_key')->toString();

        abort_unless(in_array($pageKey, SavedFilterPageKey::values(), true), 422);

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->savedFilters->listForUser($user, $pageKey)->values()->all(),
        ]);
    }

    public function store(StoreSavedFilterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payload = $this->savedFilters->upsert($user, $request->validated());

        return response()->json(['data' => $payload], 201);
    }

    public function destroy(Request $request, SavedFilter $savedFilter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->savedFilters->delete($user, $savedFilter);

        return response()->json(['ok' => true]);
    }

    public function setDefault(Request $request, SavedFilter $savedFilter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isDefault = $request->boolean('is_default', true);
        $payload = $this->savedFilters->setDefault($user, $savedFilter, $isDefault);

        return response()->json(['data' => $payload]);
    }
}
