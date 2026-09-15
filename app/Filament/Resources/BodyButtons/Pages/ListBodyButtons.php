<?php

namespace App\Filament\Resources\BodyButtons\Pages;

use App\Filament\Concerns\BustsCacheOnReorder;
use App\Filament\Resources\BodyButtons\BodyButtonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBodyButtons extends ListRecords
{
    use BustsCacheOnReorder;

    protected static string $resource = BodyButtonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
