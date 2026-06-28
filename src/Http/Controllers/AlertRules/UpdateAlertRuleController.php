<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertComparison;
use Argus\Alerting\AlertConditionType;
use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Requests\AlertRuleRequest;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class UpdateAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(AlertRuleRequest $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_ALERTS);

        $existing = $this->service->find($id);
        if ($existing === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        $v = $request->validated();
        $rule = $this->service->update(
            $id,
            $v['name'],
            (int) $v['threshold'],
            (int) $v['windowSeconds'],
            (int) $v['cooldownSeconds'],
            $v['sinks'],
            $v['enabled'] ?? $existing->enabled,
            isset($v['conditionType']) ? AlertConditionType::from($v['conditionType']) : $existing->conditionType,
            isset($v['comparison']) ? AlertComparison::from($v['comparison']) : $existing->comparison,
            array_key_exists('stuckSeconds', $v) ? ($v['stuckSeconds'] !== null ? (int) $v['stuckSeconds'] : null) : $existing->stuckSeconds,
        );

        return ApiResponse::ok(AlertRuleResource::toArray($rule));
    }
}
