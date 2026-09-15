<?php

namespace App\Filament\Resources\FabricImages\Pages;

use App\Filament\Resources\FabricImages\FabricImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFabricImages extends ListRecords
{
    protected static string $resource = FabricImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
