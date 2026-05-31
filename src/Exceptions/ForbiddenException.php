<?php

declare(strict_types=1);

namespace ArgusApi\Exceptions;

use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated user fails an authorization gate. Renders its own
 * 403 envelope. (Unauthenticated requests never reach here: the app's auth
 * middleware rejects them before any controller runs.)
 */
final class ForbiddenException extends RuntimeException
{
    public function __construct(private readonly string $ability)
    {
        parent::__construct("You are not authorized to perform [{$ability}].");
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error('forbidden', $this->getMessage(), 403, ['ability' => $this->ability]);
    }
}
