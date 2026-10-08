<?php

namespace App\Filament\Resources\CustomLiningFabrics\Tables;

use App\Filament\Support\ImageViews;
use App\Filament\Support\SwatchCards;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/* Lining cloths as swatch cards or rows, with a switch to offer or withdraw each one. */
class CustomLiningFabricsTable
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

        return ImageViews::apply($table, default: ImageViews::GRID);
    }
}
