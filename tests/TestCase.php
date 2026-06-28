<?php

declare(strict_types=1);

namespace ArgusApi\Tests;

use Argus\ArgusServiceProvider;
use Argus\Contracts\AlertFiringStore;
use Argus\Contracts\AlertRuleStore;
use Argus\Contracts\SavedSearchStore;
use Argus\Contracts\TransitionQuery;
use ArgusApi\ArgusApiServiceProvider;
use ArgusApi\Tests\Support\FakeAlertFiringStore;
use ArgusApi\Tests\Support\FakeAlertRuleStore;
use ArgusApi\Tests\Support\FakeSavedSearchStore;
use ArgusApi\Tests\Support\FakeTransitionQuery;
use Illuminate\Auth\GenericUser;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeTransitionQuery $transitions;

    protected FakeSavedSearchStore $savedSearches;

    protected FakeAlertRuleStore $alertRules;

    protected FakeAlertFiringStore $alertFirings;

    protected function getPackageProviders($app): array
    {
        return [ArgusServiceProvider::class, ArgusApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The core provider needs a declared store, but every storage contract is
        // replaced with a fake in setUp(), so nothing reaches Postgres or Redis.
        $app['config']->set('argus.store', 'postgres');
        $app['config']->set('argus.schedule.enabled', false);
        $app['config']->set('argus.alerting.enabled', false);

        // Default the API to no auth so endpoint tests exercise controllers
        // directly. Setting guard to null suppresses the derived auth middleware;
        // auth-specific tests override both values as needed.
        $app['config']->set('argus-api.guard', null);
        $app['config']->set('argus-api.middleware', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transitions = new FakeTransitionQuery;
        $this->savedSearches = new FakeSavedSearchStore;
        $this->alertRules = new FakeAlertRuleStore;
        $this->alertFirings = new FakeAlertFiringStore;

        $this->app->instance(TransitionQuery::class, $this->transitions);
        $this->app->instance(SavedSearchStore::class, $this->savedSearches);
        $this->app->instance(AlertRuleStore::class, $this->alertRules);
        $this->app->instance(AlertFiringStore::class, $this->alertFirings);
    }

    protected function actingAsUser(): static
    {
        return $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Test User']));
    }
}
