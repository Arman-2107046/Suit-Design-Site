<?php

namespace App\Filament\Resources\SleeveTypes\Pages;

use App\Filament\Concerns\BustsCacheOnReorder;
use App\Filament\Resources\SleeveTypes\SleeveTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSleeveTypes extends ListRecords
{
    use BustsCacheOnReorder;

    protected static string $resource = SleeveTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
