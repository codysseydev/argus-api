<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Exceptions\NotFoundException;
use ArgusApi\Http\Resources\TransitionRecordResource;
use ArgusApi\Http\Support\ApiResponse;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class JobHistoryController
{
    public function __construct(
        private JobQueryService $query,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(Request $request, string $jobUuid): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::VIEW_JOBS);

        $history = $this->query->getHistory($jobUuid);

        // Every recorded job has at least a QUEUED transition, so an empty
        // history means the uuid was never recorded: that is a 404, not [].
        if ($history === []) {
            throw new NotFoundException("Unknown job [{$jobUuid}].");
        }

        return ApiResponse::ok(
            TransitionRecordResource::collection($history),
            ['jobUuid' => $jobUuid, 'count' => count($history)],
        );
    }
}
