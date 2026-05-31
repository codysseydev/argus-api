<?php

declare(strict_types=1);

namespace ArgusApi\Http\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the single JSON envelope every Argus API endpoint returns. Success is
 * {"data": ..., "meta": {...}}; failure is {"error": {"type","message","details"}}.
 * Lists stay JSON arrays; meta and details are always JSON objects (an empty
 * array would otherwise serialise as []), which keeps the Phase 5 client's types
 * stable.
 */
final readonly class ApiResponse
{
    /**
     * @param  array<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function ok(array $data, array $meta = []): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => (object) $meta], 200);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function created(array $data): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => (object) []], 201);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(string $type, string $message, int $status, array $details = []): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'type' => $type,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }
}
