<?php

declare(strict_types=1);

namespace ArgusApi\Tests\Feature;

use ArgusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class UnauthenticatedTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Null guard hands full manual control back to middleware. Setting the
        // web 'auth' middleware here exercises the unauthenticated path without
        // requiring Sanctum; a guest hitting a JSON route is rejected with 401.
        $app['config']->set('argus-api.guard', null);
        $app['config']->set('argus-api.middleware', ['auth']);
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected_before_the_controller(): void
    {
        $this->postJson('argus-api/search', ['queue' => 'emails'])->assertStatus(401);
    }

    #[Test]
    public function an_authenticated_request_passes_the_middleware(): void
    {
        $this->actingAsUser()->postJson('argus-api/search', ['queue' => 'emails'])->assertOk();
    }
}
