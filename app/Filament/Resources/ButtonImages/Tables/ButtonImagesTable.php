<?php

namespace App\Filament\Resources\ButtonImages\Tables;

use App\Filament\Support\SwatchCards;
use App\Models\ButtonImage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/* Button styles as cards: the whole button on a soft ground, where it is used, and a switch. */
class ButtonImagesTable
{
    public static function configure(Table $table): Table
    {
        return SwatchCards::grid($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('bodyButtons'))
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Stack::make([
                    SwatchCards::image('diagram', fit: 'contain'),
                    Split::make([
                        TextColumn::make('name')
                            ->searchable()
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
