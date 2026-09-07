<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status, $meta);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function error(
        string $message = 'Error',
        int $status = 400,
        mixed $errors = null,
    ): JsonResponse {
        return ApiResponse::error($message, $status, $errors);
    }
}
