<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OrdersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Orders & revenue';

    protected ?string $description = 'Per day, cancelled orders excluded.';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $orders = Order::where('created_at', '>=', $from)->where('status', '!=', OrderStatus::Cancelled)->get(['created_at', 'total'])
            ->groupBy(fn (Order $o) => $o->created_at->toDateString());

        $labels = [];
        $counts = [];
        $revenue = [];
        foreach (range($days - 1, 0) as $i) {
            $day = Carbon::today()->subDays($i);
            $key = $day->toDateString();
            $labels[] = $day->format($days > 31 ? 'j M' : 'D j');
            $counts[] = isset($orders[$key]) ? $orders[$key]->count() : 0;
            $revenue[] = isset($orders[$key]) ? (float) $orders[$key]->sum('total') : 0;
        }

        return [
            'datasets' => [
                ['label' => 'Revenue ($)', 'data' => $revenue, 'type' => 'line', 'yAxisID' => 'y1', 'borderColor' => '#111827', 'backgroundColor' => 'rgba(17,24,39,0.08)', 'fill' => true, 'tension' => 0.35, 'pointRadius' => 0, 'borderWidth' => 2],
                ['label' => 'Orders', 'data' => $counts, 'backgroundColor' => 'rgba(245,158,11,0.75)', 'borderRadius' => 4, 'yAxisID' => 'y'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['display' => false]],
                'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 10]],
            ],
        ];
    }
}
