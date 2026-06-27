<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeleteSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_SAVED_SEARCHES);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        $this->service->delete($id);

        return ApiResponse::noContent();
    }
}
