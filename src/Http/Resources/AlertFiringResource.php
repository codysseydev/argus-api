<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Alerting\AlertFiring;

final readonly class AlertFiringResource
{
    /** @return array<string, mixed> */
    public static function toArray(AlertFiring $firing): array
    {
        return [
            'id' => $firing->id,
            'alertRuleId' => $firing->alertRuleId,
            'conditionType' => $firing->conditionType,
            'observedValue' => $firing->observedValue,
            'threshold' => $firing->threshold,
            'windowSeconds' => $firing->windowSeconds,
            'firedAt' => $firing->firedAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<AlertFiring>  $firings
     * @return list<array<string, mixed>>
     */
    public static function collection(array $firings): array
    {
        return array_map(self::toArray(...), $firings);
    }
}
