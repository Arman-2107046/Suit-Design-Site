<?php

namespace App\Services\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\CountryAtlas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
 * Every figure on the admin dashboard, worked out in one place so the widgets
 * agree with each other and each number can be tested once.
 *
 * Revenue is the order total of everything not cancelled — the same rule the
 * stats cards have always used. Grouping by month and country happens here in
 * PHP rather than in SQL, which keeps it identical on MySQL and in tests.
 */
class ShopMetrics
{
    /** Years the sales chart can show: the first order's through this one. */
    public function salesYears(): array
    {
        $first = Order::query()->min('created_at');
        $from = $first ? Carbon::parse($first)->year : now()->year;

        return range(now()->year, min($from, now()->year));
    }

    /**
     * Revenue per month for a year — null for months still to come, so the
     * line stops at today rather than falling to zero.
     *
     * @return array<int, float|null> twelve values, January first
     */
    public function monthlyRevenue(int $year): array
    {
        $totals = array_fill(1, 12, 0.0);

        $this->sellingOrders()
            ->whereBetween('created_at', [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->get(['created_at', 'total'])
            ->each(function (Order $order) use (&$totals) {
                $totals[$order->created_at->month] += (float) $order->total;
            });

        $months = array_values($totals);

        /* Months are 0-indexed here; this month is now()->month - 1. In December
           there is nothing ahead, and range(12, 11) would count backwards. */
        if ($year === now()->year && now()->month < 12) {
            foreach (range(now()->month, 11) as $future) {
                $months[$future] = null;
            }
        }

        return $months;
    }

    /**
     * A year's revenue against the year before. For the current year that is a
     * fair fight — the same dates last year — not this year so far against
     * the whole of last year.
     *
     * @return array{total: float, previous: float, change: ?float, partial: bool}
     */
    public function yearOnYear(int $year): array
    {
        $partial = $year === now()->year;
        $end = $partial ? now() : Carbon::create($year)->endOfYear();

        $total = $this->revenueBetween(Carbon::create($year)->startOfYear(), $end);
        $previous = $this->revenueBetween(Carbon::create($year - 1)->startOfYear(), $end->copy()->subYearNoOverflow());

        return [
            'total' => $total,
            'previous' => $previous,
            'change' => $previous > 0 ? ($total - $previous) / $previous * 100 : null,
            'partial' => $partial,
        ];
    }

    /**
     * Orders at each stage, and how long the finished ones took.
     *
     * @return array{stages: array<int, array{status: OrderStatus, count: int}>, active: int, cancelled: int, days_to_ship: ?float, days_to_deliver: ?float}
     */
    public function pipeline(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $stages = collect(OrderStatus::cases())
            ->reject(fn (OrderStatus $s) => $s === OrderStatus::Cancelled)
            ->map(fn (OrderStatus $s) => ['status' => $s, 'count' => (int) ($counts[$s->value] ?? 0)])
            ->values()
            ->all();

        return [
            'stages' => $stages,
            'active' => array_sum(array_column($stages, 'count')),
            'cancelled' => (int) ($counts[OrderStatus::Cancelled->value] ?? 0),
            'days_to_ship' => $this->averageDays('shipped_at'),
            'days_to_deliver' => $this->averageDays('delivered_at'),
        ];
    }

    /**
     * The fabrics customers order most.
     *
     * @return Collection<int, array{fabric_id: ?int, name: string, image: ?string, suits: int, revenue: float, share: float}>
     */
    public function topFabrics(int $limit = 6): Collection
    {
        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled))
            ->selectRaw('fabric_id, fabric_name, MAX(fabric_image) as fabric_image, SUM(quantity) as suits, SUM(price * quantity) as revenue')
            ->groupBy('fabric_id', 'fabric_name')
            ->orderByDesc('suits')
            ->orderByDesc('revenue')
            ->get();

        $allSuits = max(1, (int) $rows->sum('suits'));

        return $rows->take($limit)->map(fn ($row) => [
            'fabric_id' => $row->fabric_id ? (int) $row->fabric_id : null,
            'name' => $row->fabric_name,
            'image' => $row->fabric_image,
            'suits' => (int) $row->suits,
            'revenue' => (float) $row->revenue,
            'share' => (int) $row->suits / $allSuits * 100,
        ])->values();
    }

    /**
     * Where customers are, by shipping country.
     *
     * @return Collection<int, array{code: string, name: string, flag: string, coords: array{0: float, 1: float}, orders: int, customers: int, revenue: float}>
     */
    public function customersByCountry(): Collection
    {
        return $this->sellingOrders()
            ->get(['email', 'total', 'shipping'])
            ->groupBy(fn (Order $o) => CountryAtlas::code($o->shipping['country'] ?? null) ?? '??')
            ->reject(fn ($orders, $code) => $code === '??' || CountryAtlas::coordinates($code) === null)
            ->map(fn (Collection $orders, string $code) => [
                'code' => $code,
                'name' => CountryAtlas::name($code),
                'flag' => CountryAtlas::flag($code),
                'coords' => CountryAtlas::coordinates($code),
                'orders' => $orders->count(),
                'customers' => $orders->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->unique()->count(),
                'revenue' => (float) $orders->sum('total'),
            ])
            ->sortByDesc('orders')
            ->values();
    }

    /**
     * What customers design: the most chosen option in each part of the suit.
     *
     * @return array<string, array{orders: int, choices: array<int, array{name: string, count: int, share: float}>}>
     */
    public function designTrends(int $perPart = 3): array
    {
        $parts = [
            'Body style' => fn (array $d) => $d['body']['name'] ?? null,
            'Lapel' => fn (array $d) => isset($d['lapel']['categoryName'])
                ? trim($d['lapel']['categoryName'].' · '.($d['lapel']['subcategoryName'] ?? ''), ' ·')
                : null,
            'Shoulder' => fn (array $d) => $d['sleeve']['name'] ?? null,
            'Side pockets' => fn (array $d) => $d['sidePocket']['name'] ?? null,
            'Chest pocket' => fn (array $d) => $d['chestPocket']['name'] ?? null,
            'Lining' => fn (array $d) => ($d['lining']['mode'] ?? 'default') === 'custom'
                ? ($d['lining']['name'] ?? 'Custom lining')
                : 'Standard lining',
        ];

        $designs = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled))
            ->get(['design', 'quantity'])
            ->filter(fn (OrderItem $i) => is_array($i->design));

        $trends = [];

        foreach ($parts as $part => $read) {
            $tally = [];
            $orders = 0;

            foreach ($designs as $item) {
                $choice = $read($item->design);
                if ($choice === null || $choice === '') {
                    continue;
                }
                $tally[$choice] = ($tally[$choice] ?? 0) + max(1, (int) $item->quantity);
                $orders += max(1, (int) $item->quantity);
            }

            arsort($tally);

            $trends[$part] = [
                'orders' => $orders,
                'choices' => collect($tally)->take($perPart)->map(fn ($count, $name) => [
                    'name' => (string) $name,
                    'count' => $count,
                    'share' => $orders ? $count / $orders * 100 : 0,
                ])->values()->all(),
            ];
        }

        return $trends;
    }

    private function sellingOrders()
    {
        return Order::query()->where('status', '!=', OrderStatus::Cancelled);
    }

    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) $this->sellingOrders()->whereBetween('created_at', [$from, $to])->sum('total');
    }

    /** Average days from ordering to a milestone, over the orders that reached it. */
    private function averageDays(string $column): ?float
    {
        $orders = Order::query()->whereNotNull($column)->get(['created_at', $column]);

        if ($orders->isEmpty()) {
            return null;
        }

        return round($orders->avg(fn (Order $o) => $o->created_at->diffInHours(Carbon::parse($o->{$column})) / 24), 1);
    }
}
