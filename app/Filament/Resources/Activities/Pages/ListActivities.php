<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Widgets\ActivityStats;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected ?string $subheading = 'Everything the team does in the admin, as it happens. Entries cannot be edited or deleted.';

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityStats::class,
        ];
    }
}
