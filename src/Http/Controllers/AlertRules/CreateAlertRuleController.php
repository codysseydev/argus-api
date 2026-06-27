<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use Argus\SavedSearches\SavedSearchService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Requests\AlertRuleRequest;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class CreateAlertRuleController
{
    public function __construct(
        private AlertService $alerts,
        private SavedSearchService $savedSearches,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(AlertRuleRequest $request, string $savedSearchId): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_ALERTS);

        if ($this->savedSearches->find($savedSearchId) === null) {
            throw new NotFoundException("Unknown saved search [{$savedSearchId}].");
        }

        $v = $request->validated();
        $rule = $this->alerts->attach(
            $savedSearchId,
            $v['name'],
            (int) $v['threshold'],
            (int) $v['windowSeconds'],
            (int) $v['cooldownSeconds'],
            $v['sinks'],
            $v['enabled'] ?? true,
        );

        return ApiResponse::created(AlertRuleResource::toArray($rule));
    }
}
