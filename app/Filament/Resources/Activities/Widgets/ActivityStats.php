<?php

namespace App\Filament\Resources\Activities\Widgets;

use App\Models\Activity;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/* The week at a glance, above the log. */
class ActivityStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $week = now()->subDays(7);

        /* Changes per day for the last week, oldest first, for the sparkline. */
        $daily = Activity::query()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->whereIn('event', ['created', 'updated', 'deleted', 'reordered', 'bulk_upload'])
            ->get(['created_at'])
            ->countBy(fn (Activity $a) => $a->created_at->toDateString());

        $sparkline = collect(range(6, 0))->map(fn (int $daysAgo) => $daily[now()->subDays($daysAgo)->toDateString()] ?? 0)->all();

        $changesToday = Activity::query()
            ->where('created_at', '>=', today())
            ->whereIn('event', ['created', 'updated', 'deleted', 'reordered', 'bulk_upload'])
            ->count();

        $activeAdmins = Activity::query()->where('created_at', '>=', $week)->whereNotNull('admin_id')->distinct()->count('admin_id');

        $deletions = Activity::query()->where('created_at', '>=', $week)->where('event', 'deleted')->count();

        $failed = Activity::query()->where('created_at', '>=', $week)->where('event', 'login_failed')->count();

        return [
            Stat::make('Changes today', number_format($changesToday))
                ->description(array_sum($sparkline).' in the last 7 days')
                ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->chart($sparkline)
                ->color('primary'),
            Stat::make('Active admins', $activeAdmins)
                ->description('Did something this week')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('Deletions', $deletions)
                ->description('In the last 7 days')
                ->descriptionIcon(Heroicon::OutlinedTrash)
                ->color($deletions > 0 ? 'danger' : 'gray'),
            Stat::make('Failed sign-ins', $failed)
                ->description($failed > 0 ? 'Worth a look' : 'None this week')
                ->descriptionIcon($failed > 0 ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShieldCheck)
                ->color($failed > 0 ? 'warning' : 'success'),
        ];
    }
}
