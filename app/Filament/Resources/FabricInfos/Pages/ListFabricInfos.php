<?php

namespace App\Filament\Resources\FabricInfos\Pages;

use App\Filament\Resources\FabricInfos\FabricInfoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFabricInfos extends ListRecords
{
    protected static string $resource = FabricInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
