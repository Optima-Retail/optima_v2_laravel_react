<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class TabulatorResponse
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => array_values($paginator->items()),
            'last_page' => max(1, $paginator->lastPage()),
            'last_row' => $paginator->total(),
        ]);
    }
}
