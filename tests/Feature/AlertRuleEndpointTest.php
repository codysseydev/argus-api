<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
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

    #[Test]
    public function it_creates_a_rule_with_a_richer_condition(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'Slow billing',
            'threshold' => 2000,
            'conditionType' => 'latency_p95',
            'comparison' => 'gt',
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => ['slack'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.conditionType', 'latency_p95')
            ->assertJsonPath('data.comparison', 'gt')
            ->assertJsonPath('data.stuckSeconds', null);
    }

    #[Test]
    public function create_rejects_an_unknown_condition_type(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'Bad',
            'threshold' => 1,
            'conditionType' => 'not_a_condition',
            'windowSeconds' => 60,
            'cooldownSeconds' => 60,
            'sinks' => [],
        ])->assertStatus(422)->assertJsonPath('error.type', 'validation');
    }

    #[Test]
    public function stuck_count_condition_requires_a_stuck_age(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'Stuck jobs',
            'threshold' => 2,
            'conditionType' => 'stuck_count',
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => [],
        ])->assertStatus(422)->assertJsonPath('error.type', 'validation');

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'Stuck jobs',
            'threshold' => 2,
            'conditionType' => 'stuck_count',
            'stuckSeconds' => 300,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => [],
        ])->assertCreated()->assertJsonPath('data.stuckSeconds', 300);
    }

    #[Test]
    public function stuck_seconds_is_rejected_for_non_stuck_conditions(): void
    {
        $ssId = $this->savedSearchId();

        $this->actingAsUser()->postJson("argus-api/saved-searches/{$ssId}/alert-rules", [
            'name' => 'Count with stray stuck age',
            'threshold' => 5,
            'conditionType' => 'count',
            'stuckSeconds' => 300,
            'windowSeconds' => 300,
            'cooldownSeconds' => 600,
            'sinks' => [],
        ])->assertStatus(422)->assertJsonPath('error.type', 'validation');
    }

    #[Test]
    public function it_lists_firing_history_for_a_rule_most_recent_first(): void
    {
        [, $id] = $this->createRule();

        $this->alertFirings->record($id, 12.5, 10, 300, CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), 'count');
        $this->alertFirings->record($id, 15.5, 10, 300, CarbonImmutable::parse('2026-05-01T11:00:00+00:00'), 'count');
        $this->alertFirings->record('other-rule', 99.0, 1, 60, CarbonImmutable::parse('2026-05-01T12:00:00+00:00'), 'count');

        $this->actingAsUser()->getJson("argus-api/alert-rules/{$id}/firings")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.alertRuleId', $id)
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.observedValue', 15.5)
            ->assertJsonPath('data.0.firedAt', '2026-05-01T11:00:00+00:00');
    }

    #[Test]
    public function firing_history_for_an_unknown_rule_is_404(): void
    {
        $this->actingAsUser()->getJson('argus-api/alert-rules/999/firings')->assertStatus(404);
    }

    #[Test]
    public function it_lists_recent_firings_across_every_rule(): void
    {
        [, $id] = $this->createRule();

        $this->alertFirings->record($id, 12.0, 10, 300, CarbonImmutable::parse('2026-05-01T10:00:00+00:00'), 'count');
        $this->alertFirings->record('other-rule', 99.0, 1, 60, CarbonImmutable::parse('2026-05-01T12:00:00+00:00'), 'failure_rate');

        $this->actingAsUser()->getJson('argus-api/alert-firings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.conditionType', 'failure_rate');
    }
}
