<?php

namespace App\Policies;

use App\Models\Admin;

/* Managing the team is for super admins only. */
class AdminPolicy
{
    public function viewAny($user): bool
    {
        return self::isSuper($user);
    }

    public function view($user): bool
    {
        return self::isSuper($user);
    }

    public function create($user): bool
    {
        return self::isSuper($user);
    }

    public function update($user): bool
    {
        return self::isSuper($user);
    }

    /* Not yourself, and never the last super admin. */
    public function delete($user, Admin $admin): bool
    {
        return self::isSuper($user)
            && ! $user->is($admin)
            && ! $admin->isLastSuperAdmin();
    }

    /* One at a time, so each delete goes through the checks above. */
    public function deleteAny($user): bool
    {
        return false;
    }

    private static function isSuper($user): bool
    {
        return $user instanceof Admin && $user->isSuperAdmin();
    }
}
