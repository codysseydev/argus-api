<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Support;

use Argus\Contracts\SavedSearchStore;
use Argus\Query\JobFilter;
use Argus\SavedSearches\SavedSearch;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class FakeSavedSearchStore implements SavedSearchStore
{
    /** @var array<string, SavedSearch> */
    private array $items = [];

    private int $seq = 0;

    public function create(string $name, JobFilter $filter): SavedSearch
    {
        $id = (string) (++$this->seq);
        $now = CarbonImmutable::now();
        $saved = new SavedSearch($id, $name, $filter, $now, $now);
        $this->items[$id] = $saved;

        return $saved;
    }

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(string $id): ?SavedSearch
    {
        return $this->items[$id] ?? null;
    }

    public function update(string $id, string $name, JobFilter $filter): SavedSearch
    {
        $existing = $this->items[$id] ?? null;
        if ($existing === null) {
            throw new InvalidArgumentException("Unknown saved search [{$id}]");
        }
        $saved = new SavedSearch($id, $name, $filter, $existing->createdAt, CarbonImmutable::now());
        $this->items[$id] = $saved;

        return $saved;
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }
}
