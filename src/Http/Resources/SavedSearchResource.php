<?php

declare(strict_types=1);

namespace ArgusApi\Http\Resources;

use Argus\Query\FilterCodec;
use Argus\SavedSearches\SavedSearch;

final readonly class SavedSearchResource
{
    /** @return array<string, mixed> */
    public static function toArray(SavedSearch $saved): array
    {
        return [
            'id' => $saved->id,
            'name' => $saved->name,
            'filter' => (new FilterCodec)->encode($saved->filter),
            'createdAt' => $saved->createdAt->toIso8601String(),
            'updatedAt' => $saved->updatedAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<SavedSearch>  $items
     * @return list<array<string, mixed>>
     */
    public static function collection(array $items): array
    {
        return array_map(self::toArray(...), $items);
    }
}
