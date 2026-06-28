<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Alerting\AlertComparison;
use Argus\Alerting\AlertConditionType;
use Argus\Alerting\AlertRule;
use Argus\Alerting\AlertState;
use Argus\Contracts\AlertRuleStore;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class FakeAlertRuleStore implements AlertRuleStore
{
    /** @var array<string, AlertRule> */
    private array $items = [];

    private int $seq = 0;

    public function create(string $savedSearchId, string $name, int $threshold, int $windowSeconds, int $cooldownSeconds, array $sinks, bool $enabled, AlertConditionType $conditionType = AlertConditionType::COUNT, AlertComparison $comparison = AlertComparison::GREATER_THAN, ?int $stuckSeconds = null): AlertRule
    {
        $id = (string) (++$this->seq);
        $now = CarbonImmutable::now();
        $rule = new AlertRule($id, $savedSearchId, $name, $threshold, $conditionType, $comparison, $stuckSeconds, $windowSeconds, $cooldownSeconds, $sinks, $enabled, AlertState::OK, null, null, null, $now, $now);
        $this->items[$id] = $rule;

        return $rule;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->items, fn (AlertRule $r) => $r->enabled));
    }

    public function find(string $id): ?AlertRule
    {
        return $this->items[$id] ?? null;
    }

    public function forSavedSearch(string $savedSearchId): array
    {
        return array_values(array_filter($this->items, fn (AlertRule $r) => $r->savedSearchId === $savedSearchId));
    }

    public function update(string $id, string $name, int $threshold, int $windowSeconds, int $cooldownSeconds, array $sinks, bool $enabled, AlertConditionType $conditionType = AlertConditionType::COUNT, AlertComparison $comparison = AlertComparison::GREATER_THAN, ?int $stuckSeconds = null): AlertRule
    {
        $existing = $this->items[$id] ?? null;
        if ($existing === null) {
            throw new InvalidArgumentException("Unknown alert rule [{$id}]");
        }
        $rule = new AlertRule($id, $existing->savedSearchId, $name, $threshold, $conditionType, $comparison, $stuckSeconds, $windowSeconds, $cooldownSeconds, $sinks, $enabled, $existing->state, $existing->lastNotifiedAt, $existing->lastResultCount, $existing->lastEvaluatedAt, $existing->createdAt, CarbonImmutable::now());
        $this->items[$id] = $rule;

        return $rule;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    public function recordEvaluation(AlertRule $rule): void
    {
        $this->items[$rule->id] = $rule;
    }
}
