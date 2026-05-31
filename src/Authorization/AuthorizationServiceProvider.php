<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\ServiceProvider;

/**
 * Registers a default gate for each Argus ability, but only when the host app
 * has not already defined one (Gate::has). Defaults return the configured
 * allow_by_default verdict (true out of the box: authentication already proved
 * the user is valid). The app tightens authorization either by flipping that
 * config flag or by defining the same-named gates in its own provider.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(Gate $gate): void
    {
        foreach (Abilities::all() as $ability) {
            if ($gate->has($ability)) {
                continue;
            }

            $gate->define(
                $ability,
                fn (?Authenticatable $user) => (bool) config('argus-api.authorization.allow_by_default', true),
            );
        }
    }
}
