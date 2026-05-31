<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\TransitionRecord;
use Argus\Support\TransitionType;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class JobHistoryEndpointTest extends TestCase
{
    #[Test]
    public function it_returns_the_ordered_timeline_for_a_known_job(): void
    {
        $at = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $this->transitions->histories['uuid-1'] = [
            new TransitionRecord('uuid-1', 1, TransitionType::QUEUED, 1, $at, null, null, null),
            new TransitionRecord('uuid-1', 2, TransitionType::FAILED, 1, $at, 50, 'fp-1', 'boom'),
        ];

        $response = $this->actingAsUser()->getJson('argus-api/jobs/uuid-1/history');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.transition', 'queued')
            ->assertJsonPath('data.1.transition', 'failed')
            ->assertJsonPath('meta.jobUuid', 'uuid-1')
            ->assertJsonPath('meta.count', 2);
    }

    #[Test]
    public function an_unknown_job_uuid_is_a_404(): void
    {
        $response = $this->actingAsUser()->getJson('argus-api/jobs/does-not-exist/history');

        $response->assertStatus(404)
            ->assertJsonPath('error.type', 'not_found');
    }
}
