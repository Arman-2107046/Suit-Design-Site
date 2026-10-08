<?php

namespace App\Filament\Support;

use App\Filament\Concerns\HasImageViews;
use Closure;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/*
 * Lists with pictures shown two ways: a grid of picture cards, or the table
 * rows. A table sets up its rows as usual and then hands itself to apply(),
 * which swaps the rows for cards when the grid is showing. The switch lives in
 * the table toolbar (switcher()); the choice is kept by App\Filament\Concerns\HasImageViews.
 */
final class ImageViews
{
    public const GRID = 'grid';

    public const LIST = 'list';

    /**
     * @param  string  $image  the picture field on the record
     * @param  string  $title  the card's heading; may follow a relationship (fabric.name)
     * @param  array<string|Closure>  $details  a line under the heading: fields (lapelCategory.name) or fn (Model) => ?string
     * @param  string  $fit  "cover" fills the card (cloth, photos); "contain" shows the whole thing (diagrams, layers)
     * @param  string|null  $status  the on/off field, given a switch on the card; null for none
     * @param  Closure|null  $card  fn (): array, the card's columns, for a list that wants its own card
     */
    public static function apply(
        Table $table,
        string $image = 'image',
        string $title = 'name',
        array $details = [],
        string $fit = 'cover',
        ?string $status = 'status',
        string $default = self::LIST,
        ?Closure $card = null,
    ): Table {
        $page = $table->getLivewire();
        $view = $default;

        if (self::switches($page)) {
            $view = $page->currentImageView($default);
            /* Remembered on the page, so the switch knows which side to light up */
            $page->imageView = $view;
        }

        /* The same page sizes both ways, so switching never lands on a size the other view lacks */
        if ($view === self::LIST) {
            return $table->paginated([12, 24, 48, 96])->defaultPaginationPageOption(24);
        }

        /* The search box keeps looking in the same places as the rows did */
        $searchable = collect($table->getColumns())
            ->filter(fn (Column $column) => $column->isGloballySearchable())
            ->map(fn (Column $column) => $column->getName())
            ->values()
            ->all();

        $relationships = collect($details)
            ->filter(fn ($detail) => is_string($detail) && str_contains($detail, '.'))
            ->map(fn (string $detail) => (string) str($detail)->beforeLast('.'))
            ->unique()
            ->values()
            ->all();

        if ($relationships !== []) {
            $table->modifyQueryUsing(fn (Builder $query) => $query->with($relationships));
        }

        SwatchCards::grid($table)->columns($card ? $card() : [
            Stack::make(array_values(array_filter([
                SwatchCards::image($image, $fit),
                TextColumn::make($title)
                    ->weight('semibold')
                    ->placeholder('Untitled')
                    ->description(fn (Model $record) => self::detailLine($record, $details))
                    ->extraAttributes(['class' => 'ct-swatch-name']),
                $status ? SwatchCards::toggle($status) : null,
            ])))->space(3),
        ]);

        return $searchable === [] ? $table : $table->searchable($searchable);
    }

    /** A word for the card's detail line when a yes/no field is on, e.g. when('is_default', 'Default'). */
    public static function when(string $field, string $label): Closure
    {
        return fn (Model $record) => $record->getAttribute($field) ? $label : null;
    }

    /** The Grid / List switch, drawn at the start of the toolbar of a list that has one. */
    public static function switcher(array $scopes): string
    {
        $page = Livewire::current();

        if (! self::switches($page) || ! in_array($page::class, $scopes, true)) {
            return '';
        }

        return view('filament.partials.image-view-switch', ['view' => $page->imageView ?? self::LIST])->render();
    }

    private static function switches(?object $page): bool
    {
        return $page !== null && in_array(HasImageViews::class, class_uses_recursive($page), true);
    }

    /** @param  array<string|Closure>  $details */
    private static function detailLine(Model $record, array $details): ?string
    {
        $parts = collect($details)
            ->map(fn ($detail) => $detail instanceof Closure ? $detail($record) : data_get($record, $detail))
            ->filter(fn ($part) => filled($part))
            ->unique();

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }
}
