<?php

namespace App\Filament\Resources\Fabrics\Tables;

use App\Filament\Support\SwatchCards;
use App\Models\Fabric;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/* Fabrics as swatch cards: the cloth, its name and price, and a switch to show or hide it. */
class FabricsTable
{
    public static function configure(Table $table): Table
    {
        return SwatchCards::grid($table)
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Stack::make([
                    SwatchCards::image('image'),
                    Split::make([
                        TextColumn::make('name')
                            ->searchable()
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
    }
}
