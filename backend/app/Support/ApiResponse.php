<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, string|array<int, string>>  $fields
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $fields = [],
        ?string $requestId = null,
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
        ];

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return response()->json(['error' => $error], $status);
    }
}
