<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Unit;

use ArgusApi\ArgusApiServiceProvider;
use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class RouteMiddlewareTest extends TestCase
{
    #[Test]
    public function single_guard_string_derives_auth_middleware(): void
    {
        $this->app['config']->set('argus-api.guard', 'sanctum');
        $this->app['config']->set('argus-api.middleware', []);

        $provider = new ArgusApiServiceProvider($this->app);

        $this->assertSame(['auth:sanctum'], $this->deriveMiddleware($provider));
    }

    #[Test]
    public function list_of_guards_derives_comma_separated_auth_middleware(): void
    {
        $this->app['config']->set('argus-api.guard', ['web', 'admin']);
        $this->app['config']->set('argus-api.middleware', []);

        $provider = new ArgusApiServiceProvider($this->app);

        $this->assertSame(['auth:web,admin'], $this->deriveMiddleware($provider));
    }

    #[Test]
    public function null_guard_returns_supporting_middleware_only(): void
    {
        $this->app['config']->set('argus-api.guard', null);
        $this->app['config']->set('argus-api.middleware', ['throttle:60,1']);

        $provider = new ArgusApiServiceProvider($this->app);

        $this->assertSame(['throttle:60,1'], $this->deriveMiddleware($provider));
    }

    #[Test]
    public function backward_compat_sanctum_guard_with_empty_middleware_equals_original_default(): void
    {
        $this->app['config']->set('argus-api.guard', 'sanctum');
        $this->app['config']->set('argus-api.middleware', []);

        $provider = new ArgusApiServiceProvider($this->app);

        $this->assertSame(['auth:sanctum'], $this->deriveMiddleware($provider));
    }

    #[Test]
    public function support_middleware_is_prepended_before_derived_auth(): void
    {
        $this->app['config']->set('argus-api.guard', 'admin');
        $this->app['config']->set('argus-api.middleware', ['throttle:60,1']);

        $provider = new ArgusApiServiceProvider($this->app);

        $this->assertSame(['throttle:60,1', 'auth:admin'], $this->deriveMiddleware($provider));
    }

    /** @return list<string> */
    private function deriveMiddleware(ArgusApiServiceProvider $provider): array
    {
        $ref = new \ReflectionMethod($provider, 'routeMiddleware');

        return $ref->invoke($provider);
    }
}
