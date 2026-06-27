<?php

declare(strict_types=1);

namespace ArgusApi\Http\Controllers;

use Argus\Query\JobQueryService;
use ArgusApi\Authorization\Abilities;
use ArgusApi\Authorization\ActingUser;
use ArgusApi\Authorization\Authorize;
use ArgusApi\Http\Requests\SearchRequest;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Support\ApiResponse;
use ArgusApi\Http\Support\FilterInput;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;

final readonly class SearchController
{
    public function __construct(
        private JobQueryService $query,
        private FilterInput $filter,
        private Gate $gate,
        private ActingUser $actingUser,
    ) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        Authorize::check($this->gate, $this->actingUser->resolve(), Abilities::VIEW_JOBS);

        $filter = $this->filter->fromValidated($request->validated());

        return ApiResponse::ok(
            JobSummaryResource::collection($this->query->search($filter)),
            [
                'total' => $this->query->count($filter),
                'limit' => $filter->limit,
                'offset' => $filter->offset,
            ],
        );
    }
}
