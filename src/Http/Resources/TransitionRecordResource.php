<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\TransitionRecord;

final readonly class TransitionRecordResource
{
    /** @return array<string, mixed> */
    public static function toArray(TransitionRecord $record): array
    {
        return [
            'jobUuid' => $record->jobUuid,
            'sequence' => $record->sequence,
            'transition' => $record->transition->value,
            'attempt' => $record->attempt,
            'occurredAt' => $record->occurredAt->toIso8601String(),
            'durationMs' => $record->durationMs,
            'exceptionFingerprint' => $record->exceptionFingerprint,
            'exceptionMessage' => $record->exceptionMessage,
        ];
    }

    /**
     * @param  list<TransitionRecord>  $records
     * @return list<array<string, mixed>>
     */
    public static function collection(array $records): array
    {
        return array_map(self::toArray(...), $records);
    }
}
