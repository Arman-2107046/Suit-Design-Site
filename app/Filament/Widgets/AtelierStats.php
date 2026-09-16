<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\ContactMessage;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\SampleRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class AtelierStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $thisMonth = Order::where('created_at', '>=', now()->startOfMonth());
        $lastMonth = Order::whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()]);

        $revenueThisMonth = (float) (clone $thisMonth)->where('status', '!=', OrderStatus::Cancelled)->sum('total');
        $revenueLastMonth = (float) (clone $lastMonth)->where('status', '!=', OrderStatus::Cancelled)->sum('total');
        $ordersThisMonth = (clone $thisMonth)->count();
        $ordersLastMonth = (clone $lastMonth)->count();

        $daily = $this->dailyOrders(14);
        $dailyRevenue = $this->dailyRevenue(14);

        return [
            Stat::make('Revenue this month', '$' . number_format($revenueThisMonth, 0))
                ->description($this->delta($revenueThisMonth, $revenueLastMonth, 'vs last month'))
                ->descriptionIcon($revenueThisMonth >= $revenueLastMonth ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($revenueThisMonth >= $revenueLastMonth ? 'success' : 'danger')
                ->chart($dailyRevenue)
                ->color('success'),

            Stat::make('Orders this month', (string) $ordersThisMonth)
                ->description($this->delta($ordersThisMonth, $ordersLastMonth, 'vs last month'))
                ->descriptionIcon($ordersThisMonth >= $ordersLastMonth ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($ordersThisMonth >= $ordersLastMonth ? 'success' : 'danger')
                ->chart($daily)
                ->color('primary'),

            Stat::make('In the workshop', (string) Order::whereIn('status', [OrderStatus::Confirmed, OrderStatus::InProduction, OrderStatus::QualityCheck])->count())
                ->description(Order::where('status', OrderStatus::Pending)->count() . ' pending · ' . Order::where('status', OrderStatus::Shipped)->count() . ' shipped')
                ->descriptionIcon('heroicon-m-scissors')
                ->color('warning'),

            Stat::make('Average order', '$' . number_format((float) Order::where('status', '!=', OrderStatus::Cancelled)->avg('total'), 0))
                ->description(Fabric::where('status', true)->count() . ' fabrics on sale')
                ->descriptionIcon('heroicon-m-swatch')
                ->color('gray'),

            Stat::make('Inbox', (string) (ContactMessage::where('status', 'new')->count() + SampleRequest::where('status', 'requested')->count()))
                ->description(ContactMessage::where('status', 'new')->count() . ' messages · ' . SampleRequest::where('status', 'requested')->count() . ' sample requests')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('info'),
        ];
    }

    private function delta(float $current, float $previous, string $suffix): string
    {
        if ($previous <= 0) {
            return $current > 0 ? "New this month" : "No change {$suffix}";
        }
        $pct = round(($current - $previous) / $previous * 100);

        return ($pct >= 0 ? '+' : '') . $pct . "% {$suffix}";
    }

    /** @return array<int, int> */
    private function dailyOrders(int $days): array
    {
        $counts = Order::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn (Order $o) => $o->created_at->toDateString())
            ->map->count();

        return collect(range($days - 1, 0))->map(fn (int $i) => (int) ($counts[Carbon::today()->subDays($i)->toDateString()] ?? 0))->all();
    }

    /** @return array<int, float> */
    private function dailyRevenue(int $days): array
    {
        $sums = Order::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->where('status', '!=', OrderStatus::Cancelled)
            ->get(['created_at', 'total'])
            ->groupBy(fn (Order $o) => $o->created_at->toDateString())
            ->map(fn ($orders) => (float) $orders->sum('total'));

        return collect(range($days - 1, 0))->map(fn (int $i) => (float) ($sums[Carbon::today()->subDays($i)->toDateString()] ?? 0))->all();
    }
}
