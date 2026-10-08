<?php

namespace App\Filament\Resources\Fabrics\Tables;

use App\Filament\Support\ImageViews;
use App\Filament\Support\SwatchCards;
use App\Models\Fabric;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/* Fabrics as swatch cards (the cloth, its name and price, and a switch to show or hide it), or as rows. */
class FabricsTable
{
    public static function configure(Table $table): Table
    {
        $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                SwatchCards::thumbnail('image', 'Cloth'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_new')
                    ->label('New')
                    ->boolean(),
                ToggleColumn::make('status')
                    ->label('Live'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('status')->label('Shown to customers')->trueLabel('Live')->falseLabel('Hidden'),
                TernaryFilter::make('is_new')->label('New badge'),
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
                SwatchCards::image('image'),
                Split::make([
                    TextColumn::make('name')
                        ->weight('semibold')
                        /* Default and New sit under the name, so cards without them stay compact */
                        ->description(fn (Fabric $record) => implode(' · ', array_filter([
                            $record->is_default ? 'Default' : null,
                            $record->is_new ? 'New' : null,
                        ])) ?: null)
                        ->extraAttributes(['class' => 'ct-swatch-name']),
                    TextColumn::make('price')
                        ->money()
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'ct-swatch-price']),
                ]),
                SwatchCards::toggle(),
            ])->space(3),
        ]);
    }
}
