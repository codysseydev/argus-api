<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\AlertFiringResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ListAlertRuleFiringsController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::MANAGE_ALERTS);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        $limit = min(max((int) $request->query('limit', '100'), 1), 500);
        $firings = $this->service->listFirings($id, $limit);

        return ApiResponse::ok(
            AlertFiringResource::collection($firings),
            ['alertRuleId' => $id, 'count' => count($firings)],
        );
    }
}
