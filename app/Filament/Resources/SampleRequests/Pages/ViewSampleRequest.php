<?php

namespace App\Filament\Resources\SampleRequests\Pages;

use App\Filament\Resources\SampleRequests\SampleRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSampleRequest extends ViewRecord
{
    protected static string $resource = SampleRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SampleRequestResource::statusAction(),
        ];
    }
}
