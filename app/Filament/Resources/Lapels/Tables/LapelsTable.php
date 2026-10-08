<?php

namespace App\Filament\Resources\Lapels\Tables;

use App\Filament\Support\ImageViews;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LapelsTable
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
                TextColumn::make('fabric.name')
                    ->searchable(),
                /* A body has no name of its own: it is a fabric cut to a body type */
                TextColumn::make('body.bodyType.name')
                    ->label('Body')
                    ->searchable(),
                TextColumn::make('lapelCategory.name')
                    ->searchable(),
                TextColumn::make('lapelSubcategory.name')
                    ->searchable(),
                ImageColumn::make('image'),
                TextColumn::make('layer_index')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->boolean(),
                IconColumn::make('status')
                    ->boolean(),
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

        return ImageViews::apply($table, image: 'image', title: 'fabric.name', details: ['lapelCategory.name', 'lapelSubcategory.name', ImageViews::when('is_default', 'Default')], fit: 'contain');
    }
}
