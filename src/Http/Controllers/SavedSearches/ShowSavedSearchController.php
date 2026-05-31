<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_SAVED_SEARCHES);

        $saved = $this->service->find($id);
        if ($saved === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        return ApiResponse::ok(SavedSearchResource::toArray($saved));
    }
}
