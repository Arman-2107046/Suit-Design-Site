<?php

namespace App\Filament\Resources\FabricImages\Pages;

use App\Filament\Resources\FabricImages\FabricImageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFabricImage extends EditRecord
{
    protected static string $resource = FabricImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
