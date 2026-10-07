<?php

namespace App\Filament\Support;

use App\Support\ImageUrl;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/*
 * The catalogue screens (fabrics, lining cloths, buttons) as a grid of large
 * swatch cards instead of table rows. The look lives in
 * resources/views/filament/partials/swatch-cards.blade.php.
 */
final class SwatchCards
{
    /** Cards across: one on a phone, up to five on a wide screen. */
    public static function grid(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4, '2xl' => 5])
            /* Hidden items are greyed out, so what customers cannot see is obvious at a glance */
            ->recordClasses(fn (Model $record) => $record->getAttribute('status') === false ? 'ct-swatch ct-swatch-off' : 'ct-swatch')
            ->paginated([12, 24, 48, 96])
            ->defaultPaginationPageOption(24);
    }

    /**
     * The picture, at card size rather than the multi-megabyte original.
     * "cover" fills the card (cloth); "contain" shows the whole object (a button).
     */
    public static function image(string $field, string $fit = 'cover'): ImageColumn
    {
        return ImageColumn::make($field)
            ->state(fn (Model $record) => ImageUrl::sized($record->{$field}, 640))
            ->imageWidth('100%')
            ->imageHeight('auto')
            /* A picture that will not load says so, instead of showing a broken-image icon */
            ->extraImgAttributes(['class' => "ct-swatch-img ct-fit-{$fit}", 'loading' => 'lazy', 'alt' => '', 'onerror' => "this.classList.add('ct-img-missing')"])
            ->extraAttributes(['class' => 'ct-swatch-media']);
    }

    /** The on/off switch, with words beside it so its meaning is never a guess. */
    public static function toggle(string $field = 'status', string $on = 'Live', string $off = 'Hidden'): Split
    {
        return Split::make([
            TextColumn::make("{$field}_label")
                ->state(fn (Model $record) => $record->{$field} ? $on : $off)
                ->badge()
                ->color(fn (Model $record) => $record->{$field} ? 'success' : 'gray')
                ->icon(fn (Model $record) => $record->{$field} ? 'heroicon-m-eye' : 'heroicon-m-eye-slash'),
            ToggleColumn::make($field)
                ->grow(false)
                ->tooltip(fn (Model $record) => $record->{$field} ? "Switch off to hide it from customers" : "Switch on to show it to customers"),
        ])->extraAttributes(['class' => 'ct-swatch-foot']);
    }
}
