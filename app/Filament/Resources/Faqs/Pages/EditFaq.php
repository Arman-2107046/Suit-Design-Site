<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
