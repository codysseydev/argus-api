<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\FailureRequest;
use ArgusApi\Http\Resources\FailureGroupResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class FailureGroupController
{
    public function __construct(
        private JobQueryService $query,
        private FilterInput $filter,
        private Gate $gate,
    ) {}

    public function __invoke(FailureRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $request->user(), Abilities::VIEW_FAILURES);

        $groups = $this->query->groupFailures($this->filter->fromValidated($request->validated()));

        return ApiResponse::ok(
            FailureGroupResource::collection($groups),
            ['count' => count($groups)],
        );
    }
}
