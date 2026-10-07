<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Fabrics\FabricResource;
use App\Services\Dashboard\ShopMetrics;
use App\Support\ImageUrl;
use Filament\Widgets\Widget;

/*
 * The cloths customers order most, by suits made.
 */
class TopFabrics extends Widget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.widgets.top-fabrics';

    protected function getViewData(): array
    {
        return [
            'fabrics' => app(ShopMetrics::class)->topFabrics(6)->map(fn (array $fabric) => [
                ...$fabric,
                /* The originals run to several MB; a swatch needs a sliver of that. */
                'thumb' => ImageUrl::sized($fabric['image'], 96),
                'url' => $fabric['fabric_id'] ? FabricResource::getUrl('edit', ['record' => $fabric['fabric_id']]) : null,
            ]),
        ];
    }
}
