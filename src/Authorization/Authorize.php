<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

use ArgusApi\Exceptions\ForbiddenException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One place every controller funnels its gate check through, so the 403 path is
 * identical everywhere. Throws ForbiddenException (which renders the envelope)
 * when the user is denied.
 */
final readonly class Authorize
{
    public static function check(Gate $gate, ?Authenticatable $user, string $ability): void
    {
        if ($gate->forUser($user)->denies($ability)) {
            throw new ForbiddenException($ability);
        }
    }
}
