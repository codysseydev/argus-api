<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AlertRuleEndpointTest extends TestCase
{
    private function savedSearchId(): string
    {
        $response = $this->actingAsUser()->postJson('argus-api/saved-searches', [
            'name' => 'Failed emails',
            'filter' => ['queue' => 'emails', 'status' => 'failed'],
        ]);

        return (string) $response->json('data.id');
    }

    /** @return array{0:string,1:string} [savedSearchId, alertRuleId] */
    private function createRule(): array
    {
        $ssId = $this->savedSearchId();
        $response = $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'High failures',
            'threshold' => 10,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => ['slack'],
        ]);

        return [$ssId, (string) $response->json('data.id')];
    }

    #[Test]
    public function it_creates_a_rule_attached_to_a_saved_search(): void
    {
        $ssId = $this->savedSearchId();

        $response = $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'High failures',
            'threshold' => 10,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => ['slack'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.savedSearchId', $ssId)
            ->assertJsonPath('data.threshold', 10)
            ->assertJsonPath('data.state', 'ok')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.sinks', ['slack']);
    }

    #[Test]
    public function creating_a_rule_for_an_unknown_saved_search_is_404(): void
    {
        $this->actingAsUser()->postJson('argus-api/saved-searches/999/alert-rules', [
            'name' => 'x',
            'threshold' => 1,
            'windowSeconds' => 60,
            'cooldownSeconds' => 60,
            'sinks' => [],
        ])->assertStatus(404);
    }

    #[Test]
    public function it_lists_rules_for_a_saved_search(): void
    {
        [$ssId] = $this->createRule();

        $this->actingAsUser()->getJson("argus-api/saved-searches/{$ssId}/alert-rules")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.savedSearchId', $ssId);
    }

    #[Test]
    public function it_lists_all_rules(): void
    {
        $this->createRule();

        $this->actingAsUser()->getJson('argus-api/alert-rules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.count', 1);
    }

    #[Test]
    public function it_shows_updates_and_deletes_a_rule(): void
    {
        [, $id] = $this->createRule();

        $this->actingAsUser()->getJson("argus-api/alert-rules/{$id}")
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->actingAsUser()->putJson("argus-api/alert-rules/{$id}", [
            'name' => 'Renamed',
            'threshold' => 20,
            'windowSeconds' => 120,
            'cooldownSeconds' => 120,
            'sinks' => ['webhook'],
            'enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.threshold', 20)
            ->assertJsonPath('data.enabled', false);

        $this->actingAsUser()->deleteJson("argus-api/alert-rules/{$id}")->assertNoContent();
        $this->actingAsUser()->getJson("argus-api/alert-rules/{$id}")->assertStatus(404);
    }

    #[Test]
    public function create_validates_required_fields(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", ['name' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error.type', 'validation');
    }
}
