<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\SavedSearches;

use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SavedSearchResultsController
{
    public function __construct(
        private SavedSearchService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        // Returns job data, so it is gated by view-jobs rather than the
        // saved-search management ability.
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_JOBS);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown saved search [{$id}].");
        }

        $results = $this->service->results($id);

        return ApiResponse::ok(
            JobSummaryResource::collection($results),
            ['savedSearchId' => $id, 'count' => count($results)],
        );
    }
}
