<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;

/**
 * Resolves the acting user by iterating the configured guard(s) explicitly,
 * rather than relying on $request->user() which depends on the default guard
 * being set as a side effect of auth middleware ordering.
 */
final readonly class ActingUser
{
    public function __construct(private Factory $auth) {}

    public function resolve(): ?Authenticatable
    {
        foreach ((array) config('argus-api.guard', 'sanctum') as $guard) {
            if ($user = $this->auth->guard(trim($guard))->user()) {
                return $user;
            }
        }

        return $this->auth->guard()->user();
    }
}
