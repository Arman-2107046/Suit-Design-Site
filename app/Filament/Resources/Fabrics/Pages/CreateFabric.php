<?php

namespace App\Filament\Resources\Fabrics\Pages;

use App\Filament\Resources\Fabrics\FabricResource;
use App\Http\Controllers\Api\SuitConfiguratorController;
use Filament\Resources\Pages\CreateRecord;

class CreateFabric extends CreateRecord
{
    protected static string $resource = FabricResource::class;

    protected function afterCreate(): void
    {
        SuitConfiguratorController::forgetCache();
    }
}
