<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Authorization\ActingUser;
use ArgusApi\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;

final class ActingUserTest extends TestCase
{
    #[Test]
    public function resolves_user_from_the_configured_guard(): void
    {
        $user = new GenericUser(['id' => 42, 'name' => 'Admin']);

        // Configure the package to use the 'web' guard (available in Testbench
        // without any extra setup) and log the user in on that guard.
        $this->app['config']->set('argus-api.guard', 'web');
        $this->app['auth']->guard('web')->setUser($user);

        $resolved = $this->app->make(ActingUser::class)->resolve();

        $this->assertSame($user, $resolved);
    }

    #[Test]
    public function returns_first_authenticated_guard_when_a_list_is_given(): void
    {
        $user = new GenericUser(['id' => 7, 'name' => 'First Match']);

        // 'web' is first in the list and has a user; 'api' does not.
        $this->app['config']->set('argus-api.guard', ['web', 'api']);
        $this->app['auth']->guard('web')->setUser($user);

        $resolved = $this->app->make(ActingUser::class)->resolve();

        $this->assertSame($user, $resolved);
    }

    #[Test]
    public function user_on_the_configured_guard_passes_authorization_and_reaches_controller(): void
    {
        // Configure 'web' as the guard so ActingUser iterates it.
        $this->app['config']->set('argus-api.guard', 'web');
        $this->app['auth']->guard('web')->setUser(new GenericUser(['id' => 1]));

        // No auth middleware — the base TestCase sets argus-api.middleware => []
        // so this hits the controller directly; authorization uses ActingUser.
        $this->postJson('argus-api/search', ['queue' => 'emails'])->assertOk();
    }
}
