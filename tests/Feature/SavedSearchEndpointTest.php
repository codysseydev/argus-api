<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use Argus\Query\JobSummary;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class SavedSearchEndpointTest extends TestCase
{
    /** @return array{0:string} the created id */
    private function createOne(string $name = 'Failed emails'): array
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => $name,
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        return [(string) $response->json('data.id')];
    }

    #[Test]
    public function it_creates_a_saved_search_and_echoes_the_filter(): void
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => 'Failed emails',
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Failed emails')
            ->assertJsonPath('data.filter.queue', 'emails')
            ->assertJsonPath('data.filter.status', 'failed');
    }

    #[Test]
    public function it_lists_saved_searches(): void
    {
        $this->createOne('A');
        $this->createOne('B');

        $response = $this->actingAsUser()->getJson('argus-api/saved-searches');

        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.count', 2);
    }

    #[Test]
    public function it_shows_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    #[Test]
    public function it_updates_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->putJson("argus-api/saved-searches/{$id}", [
            'name' => 'Renamed',
            'filter' => ['queue' => 'sms'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.filter.queue', 'sms');
    }

    #[Test]
    public function it_deletes_a_saved_search(): void
    {
        [$id] = $this->createOne();

        $this->actingAsUser()->deleteJson("argus-api/saved-searches/{$id}")->assertNoContent();
        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}")->assertStatus(404);
    }

    #[Test]
    public function it_runs_a_saved_search_and_returns_results(): void
    {
        $this->transitions->summaries = [
            new JobSummary('uuid-1', 'App\\Jobs\\Send', 'emails', 'acme', 'failed', 1, CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), null, null, null),
        ];
        [$id] = $this->createOne();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$id}/results")
            ->assertOk()
            ->assertJsonPath('data.0.jobUuid', 'uuid-1')
            ->assertJsonPath('meta.savedSearchId', $id);
    }

    #[Test]
    public function unknown_ids_are_404(): void
    {
        $this->actingAsUser()->getJson('argus-api/saved-searches/999')->assertStatus(404);
        $this->actingAsUser()->putJson('argus-api/saved-searches/999', ['name' => 'x', 'filter' => []])->assertStatus(404);
        $this->actingAsUser()->deleteJson('argus-api/saved-searches/999')->assertStatus(404);
        $this->actingAsUser()->getJson('argus-api/saved-searches/999/results')->assertStatus(404);
    }

    #[Test]
    public function create_requires_a_name(): void
    {
        $this->actingAsUser()->postJson('argus-api/saved-searches', ['filter' => []])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'validation');
    }
}
