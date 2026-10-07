<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ShopMetrics;
use Filament\Widgets\Widget;

/*
 * Where customers are, from the countries their suits shipped to.
 *
 * Drawn with jsVectorMap, kept in public/vendor rather than loaded from a CDN
 * so the dashboard has no outside dependency.
 */
class CustomerMap extends Widget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.customer-map';

    private const VENDOR = 'vendor/jsvectormap/1.7.0';

    protected function getViewData(): array
    {
        $countries = app(ShopMetrics::class)->customersByCountry();
        $busiest = max([1, ...$countries->pluck('orders')->all()]);

        return [
            'countries' => $countries,
            'markers' => $countries->map(fn (array $c) => [
                'name' => $c['name'],
                'code' => $c['code'],
                'flag' => $c['flag'],
                'coords' => $c['coords'],
                'orders' => $c['orders'],
                'customers' => $c['customers'],
                'revenue' => round($c['revenue']),
                /* Area grows with orders, so the radius follows the square root */
                'radius' => round(5 + 11 * sqrt($c['orders'] / $busiest), 1),
            ])->values()->all(),
            'totals' => [
                'countries' => $countries->count(),
                'customers' => $countries->sum('customers'),
                'orders' => $countries->sum('orders'),
            ],
            'assets' => [
                'script' => asset(self::VENDOR.'/jsvectormap.min.js'),
                'map' => asset(self::VENDOR.'/world.js'),
                'style' => asset(self::VENDOR.'/jsvectormap.min.css'),
            ],
        ];
    }
}
