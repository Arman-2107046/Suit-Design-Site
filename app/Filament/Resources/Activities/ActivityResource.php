<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/* Read-only: what the team did, newest first. Super admins only (ActivityPolicy). */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Team';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $slug = 'activity-log';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('admin'))
            ->defaultSort('created_at', 'desc')
            ->defaultGroup(
                Group::make('created_at')
                    ->label('Day')
                    ->date()
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Activity $record) => match (true) {
                        $record->created_at->isToday() => 'Today',
                        $record->created_at->isYesterday() => 'Yesterday',
                        default => $record->created_at->format('l, j F Y'),
                    })
                    /* Newest day first, whatever the grouping direction says. */
                    ->orderQueryUsing(fn (Builder $query) => $query->orderByDesc('created_at'))
            )
            ->groupingSettingsHidden()
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->time('H:i')
                    ->dateTimeTooltip('j M Y, H:i:s')
                    ->color('gray')
                    ->sortable(),
                ViewColumn::make('admin_name')
                    ->label('Who')
                    ->view('filament.tables.columns.activity-actor')
                    ->searchable(),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (Activity $record) => $record->eventLabel())
                    ->color(fn (Activity $record) => $record->eventColor())
                    ->icon(fn (Activity $record) => $record->eventIcon()),
                TextColumn::make('subject_label')
                    ->label('What')
                    ->state(fn (Activity $record) => $record->sentence())
                    ->description(fn (Activity $record) => $record->subject_id ? $record->subjectTypeLabel().' #'.$record->subject_id : null)
                    ->searchable(['subject_label'])
                    ->wrap()
                    ->weight('medium'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn (Activity $record) => match (true) {
                        $record->event === 'updated' => $record->changeCount().' '.str('field')->plural($record->changeCount()),
                        $record->event === 'bulk_upload' && ($record->properties['failed'] ?? 0) > 0 => $record->properties['failed'].' rejected',
                        default => null,
                    })
                    ->badge()
                    ->color(fn (Activity $record) => $record->event === 'bulk_upload' ? 'warning' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP address')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('admin_id')
                    ->label('Administrator')
                    ->relationship('admin', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('event')
                    ->label('Action')
                    ->multiple()
                    ->options(Activity::eventOptions()),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type) => [$type => Activity::typeLabel($type)])
                        ->sort()
                        ->all())
                    ->searchable(),
                Filter::make('when')
                    ->schema([
                        DatePicker::make('from')->native(false)->maxDate(now()),
                        DatePicker::make('until')->native(false)->maxDate(now()),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.Carbon::parse($data['from'])->format('j M Y') : null,
                        ($data['until'] ?? null) ? 'Until '.Carbon::parse($data['until'])->format('j M Y') : null,
                    ])),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()
                    ->label('Details')
                    ->icon(Heroicon::OutlinedEye)
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (Activity $record) => $record->sentence())
                    ->modalDescription(fn (Activity $record) => $record->created_at->format('l j F Y, H:i:s'))
                    ->schema([])
                    ->modalContent(fn (Activity $record) => view('filament.activity.details', ['activity' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->recordAction('view')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateIcon(Heroicon::OutlinedClock)
            ->emptyStateHeading('Nothing logged yet')
            ->emptyStateDescription('Sign-ins, edits, deletions, reorders and bulk uploads by the team will appear here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
