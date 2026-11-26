<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function apiResponse(string $message, int $status = 200, $data = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status_code' => $status,
            'data' => $data,
        ], $status);
    }
}
