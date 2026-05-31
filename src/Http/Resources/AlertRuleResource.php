<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Alerting\AlertRule;

final readonly class AlertRuleResource
{
    /** @return array<string, mixed> */
    public static function toArray(AlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'savedSearchId' => $rule->savedSearchId,
            'name' => $rule->name,
            'threshold' => $rule->threshold,
            'windowSeconds' => $rule->windowSeconds,
            'cooldownSeconds' => $rule->cooldownSeconds,
            'sinks' => $rule->sinks,
            'enabled' => $rule->enabled,
            'state' => $rule->state->value,
            'lastNotifiedAt' => $rule->lastNotifiedAt?->toIso8601String(),
            'lastResultCount' => $rule->lastResultCount,
            'lastEvaluatedAt' => $rule->lastEvaluatedAt?->toIso8601String(),
            'createdAt' => $rule->createdAt->toIso8601String(),
            'updatedAt' => $rule->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    public static function collection(array $rules): array
    {
        return array_map(self::toArray(...), $rules);
    }
}
