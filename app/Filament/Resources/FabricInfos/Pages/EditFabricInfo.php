<?php

namespace App\Filament\Resources\FabricInfos\Pages;

use App\Filament\Resources\FabricInfos\FabricInfoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFabricInfo extends EditRecord
{
    protected static string $resource = FabricInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
