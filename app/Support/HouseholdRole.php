<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class HouseholdRole
{
    public const ADMIN = 'admin';

    public const EDITOR = 'editor';

    public const READER = 'reader';

    /** @deprecated Legacy UI value; treated as reader. */
    public const VIEWER = 'viewer';

    /** @deprecated Legacy value from early migrations / demo seed data. Treated as editor. */
    public const MEMBER = 'member';

    public static function canEdit(User $user): bool
    {
        return in_array($user->role, [self::ADMIN, self::EDITOR, self::MEMBER], true);
    }

    public static function isReader(User $user): bool
    {
        return in_array($user->role, [self::READER, self::VIEWER], true);
    }

    public static function isAdmin(User $user): bool
    {
        return $user->role === self::ADMIN;
    }

    public static function ensureCanEdit(User $user): void
    {
        if (! self::canEdit($user)) {
            throw new AuthorizationException('Olvasó jogosultság: módosítás nem engedélyezett.');
        }
    }
}
