<?php

namespace App\Policies;

use App\Models\Admin;

/*
 * The rule for every record in the admin that has no policy of its own:
 * admins create and edit, only super admins delete. Abilities left out here
 * (viewing, creating, editing, reordering) are allowed to any admin.
 */
class StaffPolicy
{
    public function delete($user): bool
    {
        return self::isSuper($user);
    }

    public function deleteAny($user): bool
    {
        return self::isSuper($user);
    }

    public function forceDelete($user): bool
    {
        return self::isSuper($user);
    }

    public function forceDeleteAny($user): bool
    {
        return self::isSuper($user);
    }

    private static function isSuper($user): bool
    {
        return $user instanceof Admin && $user->isSuperAdmin();
    }
}
