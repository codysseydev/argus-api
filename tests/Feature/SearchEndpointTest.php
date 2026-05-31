<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\JobSummary;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class SearchEndpointTest extends TestCase
{
    private function summary(string $uuid, string $queue, string $status, ?CarbonImmutable $dispatched = null): JobSummary
    {
        return new JobSummary($uuid, 'App\\Jobs\\Send', $queue, 'acme', $status, 1, $dispatched ?? CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), null, null, null);
    }

    #[Test]
    public function it_returns_matching_jobs_in_the_envelope(): void
    {
        $this->transitions->summaries = [
            $this->summary('uuid-1', 'emails', 'failed'),
            $this->summary('uuid-2', 'sms', 'failed'),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails']);

        $response->assertOk()
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('data.0.inFlight', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.limit', 100)
            ->assertJsonPath('meta.offset', 0);
    }

    #[Test]
    public function it_paginates_with_total_reflecting_the_full_match_set(): void
    {
        $this->transitions->summaries = [
            $this->summary('uuid-1', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T10:00:00+00:00')),
            $this->summary('uuid-2', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T09:00:00+00:00')),
            $this->summary('uuid-3', 'emails', 'failed', CarbonImmutable::parse('2026-05-01T08:00:00+00:00')),
        ];

        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails', 'limit' => 2, 'offset' => 0]);

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('data.1.jobUuid', 'uuid-2')
            ->assertJsonPath('meta.total', 3);
    }

    #[Test]
    public function an_empty_result_set_is_an_empty_list_not_an_error(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'nope']);

        $response->assertOk()
            ->assertExactJson(['data' => [], 'meta' => ['total' => 0, 'limit' => 100, 'offset' => 0]]);
    }

    #[Test]
    public function an_invalid_status_is_a_422_validation_envelope(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/search', ['status' => 'not-a-status']);

        $response->assertStatus(422)
            ->assertJsonPath('error.type', 'validation')
            ->assertJsonStructure(['error' => ['type', 'message', 'details' => ['status']]]);
    }
}
