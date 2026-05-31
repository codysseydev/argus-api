<?php

declare(strict_types=1);

namespace ArgusApi\Authorization;

/**
 * The four authorization abilities this package guards its endpoints with. The
 * app may redefine any of these gates in its own provider to apply real role
 * checks; the names are the contract.
 */
final class Abilities
{
    public const VIEW_JOBS = 'view-jobs';

    public const VIEW_FAILURES = 'view-failures';

    public const MANAGE_SAVED_SEARCHES = 'manage-saved-searches';

    public const MANAGE_ALERTS = 'manage-alerts';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_JOBS,
            self::VIEW_FAILURES,
            self::MANAGE_SAVED_SEARCHES,
            self::MANAGE_ALERTS,
        ];
    }
}
