<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListSavedSearchAlertRulesController
{
    public function __construct(
        private AlertService $alerts,
        private SavedSearchService $savedSearches,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(Request $request, string $savedSearchId): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_ALERTS);

        if ($this->savedSearches->find($savedSearchId) === null) {
            throw new NotFoundException("Unknown saved search [{$savedSearchId}].");
        }

        $rules = $this->alerts->forSavedSearch($savedSearchId);

        return ApiResponse::ok(
            AlertRuleResource::collection($rules),
            ['savedSearchId' => $savedSearchId, 'count' => count($rules)],
        );
    }
}
