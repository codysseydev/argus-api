<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use Argus\Support\TransitionType;
use ArgusApi\Http\Support\FilterInput;
use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FilterInputTest extends TestCase
{
    #[Test]
    public function it_maps_json_to_a_job_filter_via_the_core_codec(): void
    {
        $filter = $this->app->make(FilterInput::class)->fromValidated([
            'queue' => 'emails',
            'status' => 'failed',
            'since' => '2026-05-01T00:00:00+00:00',
            'limit' => 25,
            'offset' => 50,
        ]);

        $this->assertSame('emails', $filter->queue);
        $this->assertSame(TransitionType::FAILED, $filter->status);
        $this->assertSame('2026-05-01T00:00:00+00:00', $filter->since?->toIso8601String());
        $this->assertSame(25, $filter->limit);
        $this->assertSame(50, $filter->offset);
        $this->assertNull($filter->jobClass);
    }

    #[Test]
    public function it_applies_the_default_limit_when_absent(): void
    {
        $filter = $this->app->make(FilterInput::class)->fromValidated([]);

        $this->assertSame(100, $filter->limit);
        $this->assertSame(0, $filter->offset);
    }

    #[Test]
    public function it_clamps_the_limit_to_the_configured_maximum(): void
    {
        config()->set('argus-api.pagination.max_limit', 200);

        $filter = $this->app->make(FilterInput::class)->fromValidated(['limit' => 9999]);

        $this->assertSame(200, $filter->limit);
    }
}
