<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ShopMetrics;
use Filament\Widgets\Widget;

/*
 * What customers actually design — each part of the suit as a donut of the
 * options chosen, read from the orders themselves.
 */
class DesignTrends extends Widget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.design-trends';

    /* The sales chart's indigo and cyan, then two warm accents; grey is kept for "Other". */
    private const PALETTE = ['#4f46e5', '#06b6d4', '#f59e0b', '#10b981'];

    private const OTHER = '#cbd5e1';

    protected function getViewData(): array
    {
        $trends = app(ShopMetrics::class)->designTrends(count(self::PALETTE));

        $parts = [];
        foreach ($trends as $part => $trend) {
            $parts[$part] = $this->donut($trend['choices']);
        }

        return [
            'parts' => $parts,
            'suits' => max(array_column($trends, 'orders') ?: [0]),
        ];
    }

    /**
     * The slices of one part's donut, and the conic-gradient that draws them.
     * Whatever falls outside the top choices becomes "Other", so the slices
     * always add up to the whole and the pie never overstates anything.
     */
    private function donut(array $choices): array
    {
        $slices = [];
        foreach ($choices as $i => $choice) {
            $slices[] = ['name' => $choice['name'], 'share' => $choice['share'], 'color' => self::PALETTE[$i]];
        }

        $rest = 100 - array_sum(array_column($slices, 'share'));
        if ($rest >= 0.5) {
            $slices[] = ['name' => 'Other', 'share' => $rest, 'color' => self::OTHER];
        }

        /* A hair of space between slices reads as precision; one slice needs none. */
        $gap = count($slices) > 1 ? 0.6 : 0;
        $at = 0;
        $stops = [];

        foreach ($slices as $slice) {
            $end = $at + $slice['share'];
            $stops[] = sprintf('%s %.2f%% %.2f%%', $slice['color'], $at, max($at, $end - $gap));
            if ($gap) {
                $stops[] = sprintf('transparent %.2f%% %.2f%%', max($at, $end - $gap), $end);
            }
            $at = $end;
        }

        return [
            'slices' => $slices,
            'gradient' => $stops ? 'conic-gradient('.implode(', ', $stops).')' : null,
            'lead' => $slices[0] ?? null,
            'label' => implode(', ', array_map(fn ($s) => $s['name'].' '.round($s['share']).'%', $slices)),
        ];
    }
}
