<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers\AlertRules;

use Argus\Alerting\AlertService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class DeleteAlertRuleController
{
    public function __construct(
        private AlertService $service,
        private Gate $gate,
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::MANAGE_ALERTS);

        if ($this->service->find($id) === null) {
            throw new NotFoundException("Unknown alert rule [{$id}].");
        }

        $this->service->delete($id);

        return ApiResponse::noContent();
    }
}
