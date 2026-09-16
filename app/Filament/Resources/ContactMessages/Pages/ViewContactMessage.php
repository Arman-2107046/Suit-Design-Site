<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply by email')
                ->icon('heroicon-o-arrow-uturn-left')
                ->url(fn () => 'mailto:' . $this->record->email . '?subject=' . rawurlencode('Re: ' . ($this->record->subject ?: 'Your message')))
                ->openUrlInNewTab(),
            ContactMessageResource::statusAction(),
        ];
    }
}
