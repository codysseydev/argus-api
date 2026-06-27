<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\SaveSearchRequest;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class CreateSavedSearchController
{
    public function __construct(
        private SavedSearchService $service,
        private FilterInput $filter,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(SaveSearchRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_SAVED_SEARCHES);

        $validated = $request->validated();
        $saved = $this->service->create($validated['name'], $this->filter->fromValidated($validated['filter']));

        return ApiResponse::created(SavedSearchResource::toArray($saved));
    }
}
