<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
 * What an administrator may do. Customers are not in this at all: they live
 * in the users table and have no way into the admin.
 */
enum AdminRole: string implements HasColor, HasDescription, HasLabel
{
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'info',
            self::SuperAdmin => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Admin => 'Runs the shop day to day: orders, the catalogue, bulk upload, the journal, pages and support. Cannot delete anything, change site-wide settings, or manage the team.',
            self::SuperAdmin => 'Everything, including deleting, site-wide settings, administrators and the activity log.',
        };
    }
}
