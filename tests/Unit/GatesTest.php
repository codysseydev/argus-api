<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\Authorization\Abilities;
use ArgusApi\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Gate;
use PHPUnit\Framework\Attributes\Test;

final class GatesTest extends TestCase
{
    #[Test]
    public function all_four_abilities_are_defined(): void
    {
        $gate = $this->app->make(Gate::class);

        foreach (Abilities::all() as $ability) {
            $this->assertTrue($gate->has($ability), "gate {$ability} not defined");
        }

        $this->assertSame(
            ['view-jobs', 'view-failures', 'manage-saved-searches', 'manage-alerts'],
            Abilities::all(),
        );
    }

    #[Test]
    public function gates_allow_authenticated_users_by_default(): void
    {
        $gate = $this->app->make(Gate::class)->forUser(new GenericUser(['id' => 1]));

        $this->assertTrue($gate->allows(Abilities::VIEW_JOBS));
        $this->assertTrue($gate->allows(Abilities::MANAGE_ALERTS));
    }

    #[Test]
    public function default_verdict_follows_config(): void
    {
        config()->set('argus-api.authorization.allow_by_default', false);
        $gate = $this->app->make(Gate::class)->forUser(new GenericUser(['id' => 1]));

        $this->assertFalse($gate->allows(Abilities::VIEW_JOBS));
    }
}
