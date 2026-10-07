<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Filament\Resources\Admins\AdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdmins extends ListRecords
{
    protected static string $resource = AdminResource::class;

    protected ?string $subheading = 'Staff accounts for this admin. Admins run the shop day to day; super admins can also delete, change site-wide settings and manage the team.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create new')->icon('heroicon-m-plus'),
        ];
    }
}
