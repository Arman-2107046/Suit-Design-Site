<?php

namespace App\Filament\Concerns;

use App\Models\Admin;
use Filament\Facades\Filament;

/* Site-wide settings: hidden from admins, and a 403 if they open the URL. */
trait SuperAdminOnly
{
    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->isSuperAdmin();
    }
}
