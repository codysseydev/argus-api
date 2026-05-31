<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\FailureGroup;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class FailureGroupEndpointTest extends TestCase
{
    #[Test]
    public function it_returns_failure_groups_in_the_envelope(): void
    {
        $first = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $last = CarbonImmutable::parse('2026-05-01T12:00:00+00:00');
        $this->transitions->failureGroups = [
            new FailureGroup('fp-1', 'boom', 9, $first, $last),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/failures', ['queue' => 'emails']);

        $response->assertOk()
            ->assertJsonPath('data.0.fingerprint', 'fp-1')
            ->assertJsonPath('data.0.count', 9)
            ->assertJsonPath('meta.count', 1);
    }

    #[Test]
    public function no_failures_is_an_empty_list(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/failures', ['queue' => 'emails']);

        $response->assertOk()->assertExactJson(['data' => [], 'meta' => ['count' => 0]]);
    }
}
