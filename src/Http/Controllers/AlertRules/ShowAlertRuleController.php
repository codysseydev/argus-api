<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ShowAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        $rule = $this->service->find($id);
        if ($rule === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        return ApiResponse::ok(AlertRuleResource::toArray($rule));
    }
}
