<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ShopMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/*
 * Revenue month by month for a chosen year, laid over the year before.
 */
class SalesOverview extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Sales overview';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter ??= (string) now()->year;

        parent::mount();
    }

    protected function getFilters(): ?array
    {
        $years = app(ShopMetrics::class)->salesYears();

        return array_combine(array_map('strval', $years), array_map('strval', $years));
    }

    private function year(): int
    {
        return (int) ($this->filter ?: now()->year);
    }

    /* The year's total, and how it compares — set large, the way the eye reads a dashboard. */
    public function getDescription(): string | Htmlable | null
    {
        $year = $this->year();
        $yoy = app(ShopMetrics::class)->yearOnYear($year);

        $total = '$'.number_format($yoy['total'], 0);

        if ($yoy['change'] === null) {
            $badge = '<span style="font-size: 12px; font-weight: 500; color: #94a3b8;">No sales in '.($year - 1).' to compare with</span>';
        } else {
            $up = $yoy['change'] >= 0;
            $arrow = $up ? '&#8599;' : '&#8600;';
            $tint = $up ? 'so-up' : 'so-down';
            $versus = $yoy['partial']
                ? 'vs the same dates in '.($year - 1)
                : 'vs '.($year - 1);

            $badge = '<span class="so-change '.$tint.'">'
                .$arrow.' '.($up ? '+' : '').number_format($yoy['change'], 1).'%</span>'
                .'<span style="font-size: 12px; color: #94a3b8;">'.e($versus).'</span>';
        }

        return new HtmlString(
            '<span style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 6px;">'
            .'<span class="so-total">'.$total.'</span>'
            .$badge
            .'</span>'
        );
    }

    protected function getData(): array
    {
        $metrics = app(ShopMetrics::class);
        $year = $this->year();

        return [
            'datasets' => [
                [
                    'label' => (string) $year,
                    'data' => $metrics->monthlyRevenue($year),
                    'borderColor' => '#4f46e5',
                    'borderWidth' => 2.5,
                    'fill' => true,
                ],
                [
                    'label' => (string) ($year - 1),
                    'data' => $metrics->monthlyRevenue($year - 1),
                    'borderColor' => '#06b6d4',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 4 } },
                elements: {
                    line: { tension: 0.42 },
                    point: { radius: 0, hoverRadius: 5, hitRadius: 14, hoverBorderWidth: 2, hoverBackgroundColor: '#fff' },
                },
                datasets: {
                    line: {
                        // A soft wash under each line, fading to nothing at the axis
                        backgroundColor: (context) => {
                            const { chart, datasetIndex } = context;
                            const area = chart.chartArea;
                            if (! area) return 'transparent';
                            const [top, bottom] = datasetIndex === 0
                                ? ['rgba(79, 70, 229, 0.20)', 'rgba(79, 70, 229, 0)']
                                : ['rgba(6, 182, 212, 0.10)', 'rgba(6, 182, 212, 0)'];
                            const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                            gradient.addColorStop(0, top);
                            gradient.addColorStop(1, bottom);
                            return gradient;
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7, boxHeight: 7, padding: 18, color: '#64748b', font: { size: 12 } },
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 10,
                        boxPadding: 4,
                        usePointStyle: true,
                        titleFont: { weight: '600' },
                        callbacks: {
                            label: (ctx) => ' ' + ctx.dataset.label + ':  $' + Math.round(ctx.parsed.y ?? 0).toLocaleString(),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(148, 163, 184, 0.16)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 },
                            maxTicksLimit: 5,
                            callback: (value) => '$' + (value >= 1000 ? (value / 1000).toLocaleString() + 'k' : value),
                        },
                    },
                },
            }
        JS);
    }
}
