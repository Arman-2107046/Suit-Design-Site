<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Concerns\HasImageViews;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogPosts extends ListRecords
{
    use HasImageViews;

    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
