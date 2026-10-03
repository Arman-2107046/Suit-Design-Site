<?php

namespace App\Filament\Resources\Lapels\Pages;

use App\Filament\Concerns\BustsCacheOnReorder;
use App\Filament\Imports\LapelImporter;
use App\Filament\Resources\Lapels\LapelResource;
use App\Models\Body;
use App\Models\BodyType;
use App\Models\Fabric;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLapels extends ListRecords
{
    use BustsCacheOnReorder;

    protected static string $resource = LapelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ImportAction::make()
                ->importer(LapelImporter::class),

            Action::make('bulkUpload')
                ->label('Bulk Upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalWidth('lg')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view(
                    'filament.modals.bulk-upload-images',
                    [
                        'title' => 'Upload Lapel Images',
                        'subtitle' => 'Drag & drop lapel images here, or click to browse',
                        'filenameHint' => 'SB1_Peak_Wide_Blue Stripe[_1].png',
                        'wireMethod' => 'processUploads',
                    ]
                )),
        ];
    }
}
