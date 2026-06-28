<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Authorization\Abilities;
use ArgusApi\Tests\TestCase;
use Illuminate\Contracts\Auth\Access\Gate;
use PHPUnit\Framework\Attributes\Test;

final class AuthorizationTest extends TestCase
{
    #[Test]
    public function an_authenticated_user_passes_the_default_gate(): void
    {
        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])->assertOk();
    }

    #[Test]
    public function an_authenticated_user_without_the_gate_gets_403(): void
    {
        $this->app->make(Gate::class)->define(Abilities::VIEW_JOBS, fn ($user) => false);

        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])
            ->assertStatus(403)
            ->assertJsonPath('error.type', 'forbidden')
            ->assertJsonPath('error.details.ability', 'view-jobs');
    }

    #[Test]
    public function each_endpoint_is_guarded_by_its_own_gate(): void
    {
        $gate = $this->app->make(Gate::class);
        $gate->define(Abilities::VIEW_FAILURES, fn ($user) => false);
        $gate->define(Abilities::MANAGE_SAVED_SEARCHES, fn ($user) => false);
        $gate->define(Abilities::MANAGE_ALERTS, fn ($user) => false);

        $this->actingAsUser()->postJson('argus-api/failures', [])->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/saved-searches')->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/alert-rules')->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/alert-firings')->assertStatus(403);
        $this->actingAsUser()->getJson('argus-api/alert-rules/1/firings')->assertStatus(403);
    }
}
