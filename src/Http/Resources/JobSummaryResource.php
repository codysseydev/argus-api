<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\JobSummary;

final readonly class JobSummaryResource
{
    /** @return array<string, mixed> */
    public static function toArray(JobSummary $summary): array
    {
        return [
            'jobUuid' => $summary->jobUuid,
            'jobClass' => $summary->jobClass,
            'queue' => $summary->queue,
            'tenantId' => $summary->tenantId,
            'status' => $summary->status,
            'attempts' => $summary->attempts,
            'dispatchedAt' => $summary->dispatchedAt?->toIso8601String(),
            'finishedAt' => $summary->finishedAt?->toIso8601String(),
            'durationMs' => $summary->durationMs,
            'exceptionFingerprint' => $summary->exceptionFingerprint,
            'inFlight' => $summary->isInFlight(),
        ];
    }

    /**
     * @param  list<JobSummary>  $summaries
     * @return list<array<string, mixed>>
     */
    public static function collection(array $summaries): array
    {
        return array_map(self::toArray(...), $summaries);
    }
}
