<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Alerting\AlertFiring;
use Argus\Contracts\AlertFiringStore;
use Carbon\CarbonImmutable;

final class FakeAlertFiringStore implements AlertFiringStore
{
    /** @var list<AlertFiring> */
    private array $items = [];

    private int $seq = 0;

    public function record(
        string $alertRuleId,
        float $observedValue,
        int $threshold,
        int $windowSeconds,
        CarbonImmutable $firedAt,
        string $conditionType = 'count',
    ): AlertFiring {
        $firing = new AlertFiring(++$this->seq, $alertRuleId, $conditionType, $observedValue, $threshold, $windowSeconds, $firedAt);
        $this->items[] = $firing;

        return $firing;
    }

    /** @return list<AlertFiring> */
    public function forRule(string $alertRuleId, int $limit = 100): array
    {
        $matching = array_values(array_filter($this->items, fn (AlertFiring $f) => $f->alertRuleId === $alertRuleId));

        return $this->mostRecent($matching, $limit);
    }

    /** @return list<AlertFiring> */
    public function recent(int $limit = 100): array
    {
        return $this->mostRecent($this->items, $limit);
    }

    /**
     * @param  list<AlertFiring>  $firings
     * @return list<AlertFiring>
     */
    private function mostRecent(array $firings, int $limit): array
    {
        // Match the real Postgres/MySQL stores: fired_at DESC, id DESC.
        usort($firings, fn (AlertFiring $a, AlertFiring $b) => ($b->firedAt <=> $a->firedAt) ?: ($b->id <=> $a->id));

        return array_slice($firings, 0, $limit);
    }
}
