<?php

declare(strict_types=1);

namespace ArgusApi\Exceptions;

use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a referenced job_uuid, saved search, or alert rule does not exist.
 * Renders its own 404 envelope, so controllers need no try/catch and the package
 * never registers anything in the host app's exception handler.
 */
final class NotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error('not_found', $this->getMessage() ?: 'Resource not found.', 404);
    }
}
