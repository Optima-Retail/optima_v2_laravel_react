<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Config\FieldHelps\Services\FieldHelpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FieldHelp\ResolveFieldHelpRequest;
use App\Support\Locale;
use Illuminate\Http\JsonResponse;

final class FieldHelpController extends Controller
{
    public function __construct(
        private readonly FieldHelpService $fieldHelps,
    ) {}

    public function resolve(ResolveFieldHelpRequest $request): JsonResponse
    {
        $locale = Locale::normalize(
            $request->validated('locale') ?? app()->getLocale(),
        );

        $data = $this->fieldHelps->getForKeys(
            $request->validated('keys') ?? [],
            $locale,
        );

        return response()->json([
            'data' => $data,
            'locale' => $locale,
        ]);
    }
}
