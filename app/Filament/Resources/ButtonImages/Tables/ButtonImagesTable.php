<?php

namespace App\Filament\Resources\ButtonImages\Tables;

use App\Filament\Support\ImageViews;
use App\Filament\Support\SwatchCards;
use App\Models\ButtonImage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/* Button styles as cards or rows: the whole button on a soft ground, where it is used, and a switch. */
class ButtonImagesTable
{
    public static function configure(Table $table): Table
    {
        $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('bodyButtons'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                SwatchCards::thumbnail('diagram', 'Button'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('body_buttons_count')
                    ->label('Used on')
                    ->formatStateUsing(fn (int $state) => $state === 0 ? 'Not used yet' : "{$state} ".str('body')->plural($state))
                    ->color('gray'),
                ToggleColumn::make('status')
                    ->label('Live'),
            ])
            ->filters([
                TernaryFilter::make('status')->label('Shown to customers')->trueLabel('Live')->falseLabel('Hidden'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return ImageViews::apply($table, default: ImageViews::GRID, card: fn () => [
            Stack::make([
                SwatchCards::image('diagram', fit: 'contain'),
                Split::make([
                    TextColumn::make('name')
                        ->weight('semibold')
                        ->extraAttributes(['class' => 'ct-swatch-name']),
                    TextColumn::make('body_buttons_count')
                        ->formatStateUsing(fn (int $state) => $state === 0 ? 'Not used yet' : "On {$state} ".str('body')->plural($state))
                        ->color('gray')
                        ->size('xs')
                        ->grow(false),
                ]),
                SwatchCards::toggle(),
            ])->space(3),
        ]);
    }
}
