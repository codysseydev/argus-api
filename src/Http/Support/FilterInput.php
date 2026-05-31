<?php

declare(strict_types=1);

namespace ArgusApi\Http\Support;

use Argus\Query\FilterCodec;
use Argus\Query\JobFilter;

/**
 * Turns a validated JSON filter body into the core's JobFilter. It reuses the
 * core's FilterCodec verbatim (the one and only filter representation), then
 * applies this package's pagination policy: a default limit when the client
 * omits one and a hard clamp to the configured maximum so a request cannot ask
 * for an unbounded page.
 */
final readonly class FilterInput
{
    public function __construct(private FilterCodec $codec) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromValidated(array $data): JobFilter
    {
        $default = (int) config('argus-api.pagination.default_limit', 100);
        $max = (int) config('argus-api.pagination.max_limit', 500);

        $limit = isset($data['limit']) ? (int) $data['limit'] : $default;
        $data['limit'] = min($limit, $max);
        $data['offset'] = isset($data['offset']) ? (int) $data['offset'] : 0;

        return $this->codec->decode($data);
    }
}
