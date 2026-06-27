<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListSavedSearchesController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_SAVED_SEARCHES);

        $all = $this->service->all();

        return ApiResponse::ok(SavedSearchResource::collection($all), ['count' => count($all)]);
    }
}
