<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListAlertRulesController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        $all = $this->service->all();

        return ApiResponse::ok(AlertRuleResource::collection($all), ['count' => count($all)]);
    }
}
