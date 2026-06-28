<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Contracts\TransitionQuery;
use Argus\Query\FailureGroup;
use Argus\Query\JobFilter;
use Argus\Query\JobSummary;
use Argus\Query\TransitionRecord;
use Carbon\CarbonImmutable;

/**
 * In-memory TransitionQuery for tests. Applies the JobFilter predicates so the
 * HTTP layer's filter mapping and pagination can be asserted end to end without
 * a database. Records the last filter it received so tests can prove the
 * controller delegated through the query seam. Correlation predicates are not
 * modelled (no endpoint test needs them); every other predicate is.
 */
final class FakeTransitionQuery implements TransitionQuery
{
    /** @var list<JobSummary> */
    public array $summaries = [];

    /** @var array<string, list<TransitionRecord>> */
    public array $histories = [];

    /** @var list<FailureGroup> */
    public array $failureGroups = [];

    public ?JobFilter $lastFilter = null;

    /**
     * Alerting aggregates the core computes against storage. The API never calls
     * these (it only proxies the read endpoints), but the contract requires them,
     * so they return test-controllable defaults and record the filter received.
     */
    public float $failureRateValue = 0.0;

    public int $stuckCount = 0;

    public ?float $latencyMs = null;

    public function search(JobFilter $filter): array
    {
        $this->lastFilter = $filter;

        $matched = array_values(array_filter($this->summaries, fn (JobSummary $s) => $this->matches($s, $filter)));

        usort($matched, function (JobSummary $a, JobSummary $b): int {
            $av = $a->dispatchedAt?->getTimestamp();
            $bv = $b->dispatchedAt?->getTimestamp();
            if ($av === $bv) {
                return 0;
            }
            if ($av === null) {
                return 1;
            }
            if ($bv === null) {
                return -1;
            }

            return $bv <=> $av;
        });

        return array_slice($matched, $filter->offset, $filter->limit);
    }

    public function count(JobFilter $filter): int
    {
        $this->lastFilter = $filter;

        return count(array_filter($this->summaries, fn (JobSummary $s) => $this->matches($s, $filter)));
    }

    public function history(string $jobUuid): array
    {
        return $this->histories[$jobUuid] ?? [];
    }

    public function groupFailures(JobFilter $filter): array
    {
        $this->lastFilter = $filter;

        return array_values(array_filter($this->failureGroups, function (FailureGroup $g) use ($filter): bool {
            if ($filter->since !== null && $g->lastSeen->lessThan($filter->since)) {
                return false;
            }
            if ($filter->until !== null && $g->firstSeen->greaterThan($filter->until)) {
                return false;
            }

            return true;
        }));
    }

    public function failureRate(JobFilter $filter): float
    {
        $this->lastFilter = $filter;

        return $this->failureRateValue;
    }

    public function countStuck(JobFilter $filter, CarbonImmutable $stuckBefore): int
    {
        $this->lastFilter = $filter;

        return $this->stuckCount;
    }

    public function latencyPercentile(JobFilter $filter, float $percentile): ?float
    {
        $this->lastFilter = $filter;

        return $this->latencyMs;
    }

    private function matches(JobSummary $s, JobFilter $f): bool
    {
        if ($f->jobClass !== null && $s->jobClass !== $f->jobClass) {
            return false;
        }
        if ($f->queue !== null && $s->queue !== $f->queue) {
            return false;
        }
        if ($f->tenantId !== null && $s->tenantId !== $f->tenantId) {
            return false;
        }
        if ($f->status !== null && $s->status !== $f->status->value) {
            return false;
        }
        if ($f->attemptMin !== null && $s->attempts < $f->attemptMin) {
            return false;
        }
        if ($f->attemptMax !== null && $s->attempts > $f->attemptMax) {
            return false;
        }
        if ($f->since !== null && ($s->dispatchedAt === null || $s->dispatchedAt->lessThan($f->since))) {
            return false;
        }
        if ($f->until !== null && ($s->dispatchedAt === null || $s->dispatchedAt->greaterThan($f->until))) {
            return false;
        }

        return true;
    }
}
