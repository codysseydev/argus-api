<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\FailureGroup;

final readonly class FailureGroupResource
{
    /** @return array<string, mixed> */
    public static function toArray(FailureGroup $group): array
    {
        return [
            'fingerprint' => $group->fingerprint,
            'representativeMessage' => $group->representativeMessage,
            'count' => $group->count,
            'firstSeen' => $group->firstSeen->toIso8601String(),
            'lastSeen' => $group->lastSeen->toIso8601String(),
        ];
    }

    /**
     * @param  list<FailureGroup>  $groups
     * @return list<array<string, mixed>>
     */
    public static function collection(array $groups): array
    {
        return array_map(self::toArray(...), $groups);
    }
}
