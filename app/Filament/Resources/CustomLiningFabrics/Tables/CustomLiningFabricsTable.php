<?php

namespace App\Filament\Resources\CustomLiningFabrics\Tables;

use App\Filament\Support\SwatchCards;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/* Lining cloths as swatch cards, with a switch to offer or withdraw each one. */
class CustomLiningFabricsTable
{
    public static function configure(Table $table): Table
    {
        return SwatchCards::grid($table)
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Stack::make([
                    SwatchCards::image('image'),
                    TextColumn::make('name')
                        ->searchable()
                        ->weight('semibold')
                        ->extraAttributes(['class' => 'ct-swatch-name']),
                    SwatchCards::toggle(),
                ])->space(3),
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
    }
}
