<?php

namespace App\Filament\Pages;

use App\Models\ImageHealthRun;
use App\Models\ImageIssue;
use App\Services\ImageHealth\ImageHealthCheck;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/*
 * Every picture the site uses, tested: which ones do not load, why, and a
 * link straight to where each one is uploaded. Runs nightly, and on demand.
 */
class ImageHealth extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Image health';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Image health';

    protected ?string $subheading = 'Every picture on the site, tested: fabrics, layers, linings, buttons, the homepage and the journal.';

    public static function getNavigationBadge(): ?string
    {
        $broken = ImageIssue::query()->count();

        return $broken > 0 ? (string) $broken : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pictures that do not load';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.image-health.summary')->viewData(fn () => [
                'run' => ImageHealthRun::latestFinished(),
                'bySource' => ImageIssue::query()->selectRaw('source, count(*) as total')->groupBy('source')->orderByDesc('total')->pluck('total', 'source'),
            ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ImageIssue::query())
            ->defaultSort('source')
            ->columns([
                TextColumn::make('label')
                    ->label('Where')
                    ->description(fn (ImageIssue $record) => $record->source.' · '.str($record->field)->replace('_url', '')->replace('_', ' '))
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('reason')
                    ->label('Problem')
                    ->badge()
                    ->formatStateUsing(fn (ImageIssue $record) => $record->reasonLabel())
                    ->tooltip(fn (ImageIssue $record) => $record->reasonText())
                    ->color(fn (string $state) => match ($state) {
                        'server_error', 'unreachable' => 'warning',
                        'missing' => 'gray',
                        default => 'danger',
                    }),
                IconColumn::make('hidden')
                    ->label('Customers see it')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye-slash')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('gray')
                    ->falseColor('danger')
                    ->tooltip(fn (ImageIssue $record) => $record->hidden ? 'Hidden from customers, so less urgent' : 'Live on the site')
                    ->alignCenter(),
                TextColumn::make('url')
                    ->label('Address')
                    ->limit(48)
                    ->tooltip(fn (ImageIssue $record) => $record->url)
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray')
                    ->copyable()
                    ->placeholder('No picture')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')->label('Where')->options(fn () => ImageIssue::query()->distinct()->orderBy('source')->pluck('source', 'source'))->multiple(),
                SelectFilter::make('reason')->label('Problem')->options(ImageIssue::reasonOptions()),
                TernaryFilter::make('hidden')->label('Shown to customers')->trueLabel('Hidden only')->falseLabel('Live only'),
            ])
            ->recordActions([
                Action::make('fix')
                    ->label('Upload again')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->button()
                    ->size('sm')
                    ->url(fn (ImageIssue $record) => $record->edit_url)
                    ->visible(fn (ImageIssue $record) => filled($record->edit_url)),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->emptyStateHeading(fn () => ImageHealthRun::latestFinished() ? 'Every picture loads' : 'Not checked yet')
            ->emptyStateDescription(fn () => ImageHealthRun::latestFinished() ? 'Nothing to fix. The check runs again every night.' : 'Press “Check now” to test every picture on the site.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk')
                ->label('Bulk upload')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('gray')
                ->url(BulkUpload::getUrl()),

            Action::make('check')
                ->label('Check now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (ImageHealthCheck $check) {
                    $run = $check->run();

                    if ($run->error) {
                        Notification::make()->title('The check stopped')->body($run->error)->danger()->persistent()->send();

                        return;
                    }

                    $body = sprintf('%s pictures tested in %ss.', number_format($run->checked_count), $run->seconds());

                    $run->broken_count === 0
                        ? Notification::make()->title('Every picture loads')->body($body)->success()->send()
                        : Notification::make()->title($run->broken_count.' '.str('picture')->plural($run->broken_count).' to fix')->body($body)->warning()->send();
                }),
        ];
    }
}
