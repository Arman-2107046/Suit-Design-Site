<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\Dashboard\ShopMetrics;
use Filament\Widgets\Widget;

/*
 * Where every live order is, from first review to the customer's door.
 */
class OrderPipeline extends Widget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected string $view = 'filament.widgets.order-pipeline';

    /** Each stage's colour, in the order an order moves through them. */
    private const TINT = [
        'pending' => '#94a3b8',
        'confirmed' => '#0ea5e9',
        'in_production' => '#f59e0b',
        'quality_check' => '#f97316',
        'shipped' => '#6366f1',
        'delivered' => '#10b981',
    ];

    protected function getViewData(): array
    {
        $pipeline = app(ShopMetrics::class)->pipeline();
        $widest = max([1, ...array_column($pipeline['stages'], 'count')]);

        return [
            'active' => $pipeline['active'],
            'cancelled' => $pipeline['cancelled'],
            'daysToShip' => $pipeline['days_to_ship'],
            'daysToDeliver' => $pipeline['days_to_deliver'],
            'stages' => array_map(fn (array $stage) => [
                'label' => $stage['status']->getLabel(),
                'count' => $stage['count'],
                'width' => $stage['count'] / $widest * 100,
                'share' => $pipeline['active'] ? round($stage['count'] / $pipeline['active'] * 100) : 0,
                'tint' => self::TINT[$stage['status']->value] ?? '#94a3b8',
                'url' => $this->ordersAt($stage['status']),
            ], $pipeline['stages']),
        ];
    }

    private function ordersAt(OrderStatus $status): string
    {
        return OrderResource::getUrl('index', ['filters' => ['status' => ['values' => [$status->value]]]]);
    }
}
