<?php

namespace App\Policies;

use App\Models\Admin;

/* Super admins read the log. Nobody writes to it or deletes from it by hand. */
class ActivityPolicy
{
    public function viewAny($user): bool
    {
        return $user instanceof Admin && $user->isSuperAdmin();
    }

    public function view($user): bool
    {
        return $this->viewAny($user);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }
}
