<?php

namespace App\Filament\Resources\CustomLinings\Pages;

use App\Filament\Concerns\BustsCacheOnReorder;
use App\Filament\Resources\CustomLinings\CustomLiningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomLinings extends ListRecords
{
    use BustsCacheOnReorder;

    protected static string $resource = CustomLiningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
