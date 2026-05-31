<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use Argus\Alerting\AlertRule;
use Argus\Alerting\AlertState;
use Argus\Query\FailureGroup;
use Argus\Query\JobFilter;
use Argus\Query\JobSummary;
use Argus\Query\TransitionRecord;
use Argus\SavedSearches\SavedSearch;
use Argus\Support\TransitionType;
use ArgusApi\Http\Resources\AlertRuleResource;
use ArgusApi\Http\Resources\FailureGroupResource;
use ArgusApi\Http\Resources\JobSummaryResource;
use ArgusApi\Http\Resources\SavedSearchResource;
use ArgusApi\Http\Resources\TransitionRecordResource;
use ArgusApi\Tests\TestCase;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;

final class ResourcesTest extends TestCase
{
    #[Test]
    public function job_summary_includes_derived_in_flight(): void
    {
        $dispatched = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $summary = new JobSummary('uuid-1', 'App\\Jobs\\Send', 'emails', 'acme', 'processing', 2, $dispatched, null, null, null);

        $this->assertSame([
            'jobUuid' => 'uuid-1',
            'jobClass' => 'App\\Jobs\\Send',
            'queue' => 'emails',
            'tenantId' => 'acme',
            'status' => 'processing',
            'attempts' => 2,
            'dispatchedAt' => '2026-05-01T10:00:00+00:00',
            'finishedAt' => null,
            'durationMs' => null,
            'exceptionFingerprint' => null,
            'inFlight' => true,
        ], JobSummaryResource::toArray($summary));
    }

    #[Test]
    public function transition_record_renders_enum_as_value(): void
    {
        $at = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $record = new TransitionRecord('uuid-1', 3, TransitionType::FAILED, 2, $at, 120, 'fp-9', 'boom');

        $array = TransitionRecordResource::toArray($record);

        $this->assertSame('failed', $array['transition']);
        $this->assertSame(3, $array['sequence']);
        $this->assertSame('2026-05-01T10:00:00+00:00', $array['occurredAt']);
        $this->assertSame('boom', $array['exceptionMessage']);
    }

    #[Test]
    public function failure_group_renders_counts_and_window(): void
    {
        $first = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $last = CarbonImmutable::parse('2026-05-01T12:00:00+00:00');
        $group = new FailureGroup('fp-9', 'boom', 7, $first, $last);

        $this->assertSame([
            'fingerprint' => 'fp-9',
            'representativeMessage' => 'boom',
            'count' => 7,
            'firstSeen' => '2026-05-01T10:00:00+00:00',
            'lastSeen' => '2026-05-01T12:00:00+00:00',
        ], FailureGroupResource::toArray($group));
    }

    #[Test]
    public function saved_search_embeds_the_encoded_filter(): void
    {
        $now = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $saved = new SavedSearch('7', 'Failed emails', new JobFilter(queue: 'emails', status: TransitionType::FAILED), $now, $now);

        $array = SavedSearchResource::toArray($saved);

        $this->assertSame('7', $array['id']);
        $this->assertSame('Failed emails', $array['name']);
        $this->assertSame('emails', $array['filter']['queue']);
        $this->assertSame('failed', $array['filter']['status']);
        $this->assertSame('2026-05-01T10:00:00+00:00', $array['createdAt']);
    }

    #[Test]
    public function alert_rule_renders_state_and_sinks(): void
    {
        $now = CarbonImmutable::parse('2026-05-01T10:00:00+00:00');
        $rule = new AlertRule('5', '7', 'High failures', 10, 300, 600, ['slack'], true, AlertState::OK, null, null, null, $now, $now);

        $array = AlertRuleResource::toArray($rule);

        $this->assertSame('5', $array['id']);
        $this->assertSame('7', $array['savedSearchId']);
        $this->assertSame(10, $array['threshold']);
        $this->assertSame(['slack'], $array['sinks']);
        $this->assertTrue($array['enabled']);
        $this->assertSame('ok', $array['state']);
        $this->assertNull($array['lastNotifiedAt']);
    }
}
