<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Widgets\Widget;

/* The last few things the team did, for super admins — the only ones who read the log. */
class RecentActivity extends Widget
{
    protected static ?int $sort = 9;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-activity';

    public static function canView(): bool
    {
        return ActivityResource::canViewAny();
    }

    protected function getViewData(): array
    {
        return [
            'activities' => Activity::query()->with('admin')->latest('created_at')->latest('id')->limit(8)->get(),
            'logUrl' => ActivityResource::getUrl('index'),
        ];
    }
}
