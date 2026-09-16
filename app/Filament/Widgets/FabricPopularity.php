<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class FabricPopularity extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Most ordered cloths';

    protected ?string $description = 'Suits ordered per fabric, all time.';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled))
            ->selectRaw('fabric_name, SUM(quantity) as suits')
            ->groupBy('fabric_name')
            ->orderByDesc('suits')
            ->limit(6)
            ->get();

        $palette = ['#111827', '#374151', '#6b7280', '#9ca3af', '#d1d5db', '#f59e0b'];

        return [
            'datasets' => [[
                'data' => $rows->pluck('suits')->map(fn ($v) => (int) $v)->all(),
                'backgroundColor' => array_slice($palette, 0, max(1, $rows->count())),
                'borderWidth' => 0,
            ]],
            'labels' => $rows->pluck('fabric_name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
