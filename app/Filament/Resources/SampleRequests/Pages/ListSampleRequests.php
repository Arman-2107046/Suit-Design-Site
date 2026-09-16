<?php

namespace App\Filament\Resources\SampleRequests\Pages;

use App\Filament\Resources\SampleRequests\SampleRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListSampleRequests extends ListRecords
{
    protected static string $resource = SampleRequestResource::class;
}
