<?php

namespace App\Filament\Resources\LapelSubcategories\Tables;

use App\Filament\Support\ImageViews;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LapelSubcategoriesTable
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
                TextColumn::make('name')
                    ->label('Subcategory Name')
                    ->searchable()
                    ->sortable(),

                ImageColumn::make('diagram')
                    ->label('Diagram'),

                IconColumn::make('status')
                    ->boolean()
                    ->sortable(),

                /* Every fabric starts on this lapel width; switching one on switches the others off. */
                ToggleColumn::make('is_default')
                    ->label('Default')
                    ->tooltip('The lapel width every fabric starts on'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return ImageViews::apply($table, image: 'diagram', details: [ImageViews::when('is_default', 'Default')], fit: 'contain');
    }
}
