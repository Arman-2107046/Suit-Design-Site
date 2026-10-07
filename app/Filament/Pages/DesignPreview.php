<?php

namespace App\Filament\Pages;

use App\Models\Body;
use App\Models\BodyButton;
use App\Models\ChestPocket;
use App\Models\CustomLining;
use App\Models\DefaultLining;
use App\Models\Fabric;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use App\Models\SidePocket;
use App\Models\Sleeve;
use App\Models\SleeveType;
use App\Support\ImageUrl;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * The suit as the customer will see it, assembled from the same layers in
 * the same order as the designer on the site, for checking a fabric's
 * pictures before they go live. Hidden items can be previewed too.
 *
 * @property-read Schema $form
 */
class DesignPreview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Design preview';

    protected static string|\UnitEnum|null $navigationGroup = 'Jacket';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Design preview';

    protected ?string $subheading = 'The suit exactly as the designer on the site stacks it. Pick any combination, including hidden ones, to check the pictures line up.';

    /** URL keys => form fields, for links from the layer edit pages */
    private const PARAMS = [
        'fabric' => 'fabric_id', 'body' => 'body_id', 'lapel' => 'lapel_id', 'sleeve' => 'sleeve_id',
        'side_pocket' => 'side_pocket_id', 'chest_pocket' => 'chest_pocket_id', 'button' => 'body_button_id', 'lining' => 'custom_lining_id',
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** The layer the admin came here to check, highlighted in the list */
    public ?string $focus = null;

    public function mount(): void
    {
        $query = request()->query();
        $fabricId = (int) ($query['fabric'] ?? 0) ?: (Fabric::query()->where('is_default', true)->value('id') ?? Fabric::query()->orderBy('sort_order')->value('id'));

        $state = $this->defaultsFor($fabricId);
        foreach (self::PARAMS as $param => $field) {
            if (filled($query[$param] ?? null) && $param !== 'fabric') {
                $state[$field] = (int) $query[$param];
            }
        }

        /* A body or lapel picked by link brings its own buttons and lapel along */
        if (filled($query['body'] ?? null) && blank($query['lapel'] ?? null)) {
            $state['lapel_id'] = $this->defaultLapelId($state['body_id']);
        }
        if (filled($query['body'] ?? null) && blank($query['button'] ?? null)) {
            $state['body_button_id'] = $this->defaultButtonId($state['body_id']);
        }

        $this->focus = $query['focus'] ?? null;
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $hidden = fn (Model $m) => self::isHidden($m) ? ' (hidden)' : '';

        return $schema
            ->components([
                Section::make('Choose the design')
                    ->compact()
                    ->components([
                        Select::make('fabric_id')
                            ->label('Fabric')
                            ->options(fn () => Fabric::query()->orderBy('sort_order')->get()->mapWithKeys(fn (Fabric $f) => [$f->id => $f->name.$hidden($f)]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (?int $state, Set $set, Get $get) => $this->applyFabric($state, $set, $get)),
                        Select::make('body_id')
                            ->label('Body style')
                            ->options(fn (Get $get) => Body::query()->with('bodyType')->where('fabric_id', $get('fabric_id'))->get()
                                ->sortBy(fn (Body $b) => [$b->bodyType?->sort_order, $b->id])
                                ->mapWithKeys(fn (Body $b) => [$b->id => ($b->bodyType?->name ?? "Body #{$b->id}").$hidden($b)]))
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set) {
                                $set('lapel_id', $this->defaultLapelId($state));
                                $set('body_button_id', $this->defaultButtonId($state));
                            }),
                        Select::make('lapel_id')
                            ->label('Lapel')
                            ->options(fn (Get $get) => Lapel::query()->with(['lapelCategory', 'lapelSubcategory'])->where('body_id', $get('body_id'))->get()
                                ->mapWithKeys(fn (Lapel $l) => [$l->id => trim(($l->lapelCategory?->name ?? 'Lapel').' · '.($l->lapelSubcategory?->name ?? ''), ' ·').$hidden($l)]))
                            ->searchable()
                            ->live(),
                        Grid::make(2)->components([
                            Select::make('sleeve_id')
                                ->label('Shoulder')
                                ->options(fn (Get $get) => Sleeve::query()->with('sleeveType')->where('fabric_id', $get('fabric_id'))->get()
                                    ->mapWithKeys(fn (Sleeve $s) => [$s->id => ($s->sleeveType?->name ?? $s->name ?? "#{$s->id}").$hidden($s)]))
                                ->live(),
                            Select::make('body_button_id')
                                ->label('Buttons')
                                ->options(fn (Get $get) => BodyButton::query()->with('buttonImage')
                                    ->where('body_type_id', Body::query()->whereKey($get('body_id'))->value('body_type_id'))->get()
                                    ->mapWithKeys(fn (BodyButton $b) => [$b->id => ($b->buttonImage?->name ?? "#{$b->id}").$hidden($b)]))
                                ->placeholder('None')
                                ->live(),
                            Select::make('side_pocket_id')
                                ->label('Side pockets')
                                ->options(fn (Get $get) => SidePocket::query()->with('sidePocketType')->where('fabric_id', $get('fabric_id'))->get()
                                    ->mapWithKeys(fn (SidePocket $p) => [$p->id => ($p->sidePocketType?->name ?? "#{$p->id}").$hidden($p)]))
                                ->placeholder('None')
                                ->live(),
                            Select::make('chest_pocket_id')
                                ->label('Chest pocket')
                                ->options(fn (Get $get) => ChestPocket::query()->with('chestPocketType')->where('fabric_id', $get('fabric_id'))->get()
                                    ->mapWithKeys(fn (ChestPocket $p) => [$p->id => ($p->chestPocketType?->name ?? "#{$p->id}").$hidden($p)]))
                                ->placeholder('None')
                                ->live(),
                        ]),
                        Select::make('custom_lining_id')
                            ->label('Lining')
                            ->options(fn () => CustomLining::query()->with(['customLiningFabric', 'liningType'])->get()
                                ->mapWithKeys(fn (CustomLining $l) => [$l->id => trim(($l->customLiningFabric?->name ?? 'Lining').' · '.($l->liningType?->name ?? ''), ' ·').$hidden($l)]))
                            ->placeholder('The fabric’s own lining')
                            ->searchable()
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 5])->components([
                Grid::make(1)->columnSpan(['lg' => 2])->components([
                    \Filament\Schemas\Components\EmbeddedSchema::make('form'),
                ]),
                View::make('filament.pages.design-preview.canvas')
                    ->columnSpan(['lg' => 3])
                    ->viewData(fn () => ['layers' => $this->layers(), 'focus' => $this->focus]),
            ]),
        ]);
    }

    /**
     * The layers, bottom first: the same pushes and depth defaults as
     * layersFrom() in resources/js/Pages/Welcome.jsx, and a stable sort, so
     * equal depths stay in this order exactly as they do on the site.
     *
     * @return list<array{type: string, label: string, name: string, image: string, z: int, hidden: bool, edit: ?string}>
     */
    public function layers(): array
    {
        $d = $this->data ?? [];
        $body = Body::query()->with('bodyType')->find($d['body_id'] ?? null);

        $defaultLining = $body
            ? DefaultLining::query()->with('liningType')->where('fabric_id', $body->fabric_id)->where('body_type_id', $body->body_type_id)
                ->orderByDesc('status')->orderBy('sort_order')->orderBy('id')->first()
            : null;

        $picked = [
            ['defaultLining', 'Fabric lining', $defaultLining, 0, fn (DefaultLining $m) => $m->liningType?->name],
            ['lining', 'Custom lining', CustomLining::query()->with(['customLiningFabric', 'liningType'])->find($d['custom_lining_id'] ?? null), 100, fn (CustomLining $m) => $m->customLiningFabric?->name],
            ['body', 'Body', $body, 100, fn (Body $m) => $m->bodyType?->name],
            ['sleeve', 'Shoulder', Sleeve::query()->with('sleeveType')->find($d['sleeve_id'] ?? null), 150, fn (Sleeve $m) => $m->sleeveType?->name],
            ['lapel', 'Lapel', Lapel::query()->with(['lapelCategory', 'lapelSubcategory'])->find($d['lapel_id'] ?? null), 150, fn (Lapel $m) => trim(($m->lapelCategory?->name ?? '').' · '.($m->lapelSubcategory?->name ?? ''), ' ·')],
            ['sidePocket', 'Side pockets', SidePocket::query()->with('sidePocketType')->find($d['side_pocket_id'] ?? null), 100, fn (SidePocket $m) => $m->sidePocketType?->name],
            ['chestPocket', 'Chest pocket', ChestPocket::query()->with('chestPocketType')->find($d['chest_pocket_id'] ?? null), 100, fn (ChestPocket $m) => $m->chestPocketType?->name],
            ['button', 'Buttons', BodyButton::query()->with('buttonImage')->find($d['body_button_id'] ?? null), 120, fn (BodyButton $m) => $m->buttonImage?->name],
        ];

        $layers = [];
        foreach ($picked as [$type, $label, $record, $defaultZ, $name]) {
            if (! $record || blank($record->image)) {
                continue;
            }

            $layers[] = [
                'type' => $type,
                'label' => $label,
                'name' => (string) ($name($record) ?: "#{$record->getKey()}"),
                'image' => ImageUrl::sized($record->image, 1200),
                'raw' => $record->image,
                'z' => (int) ($record->layer_index ?: $defaultZ),
                'hidden' => self::isHidden($record),
                'edit' => $this->editUrl($record),
            ];
        }

        usort($layers, fn (array $a, array $b) => $a['z'] <=> $b['z']);

        return $layers;
    }

    /** The "Preview in suit" button on each layer's edit page. */
    public static function previewAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('previewInSuit')
            ->label('Preview in suit')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(fn (Model $record) => static::urlFor($record));
    }

    /** A link to this page showing a given layer in its suit, for the layer edit pages. */
    public static function urlFor(Model $record): string
    {
        $params = match (true) {
            $record instanceof Body => ['fabric' => $record->fabric_id, 'body' => $record->id, 'focus' => 'body'],
            $record instanceof Lapel => ['fabric' => $record->fabric_id, 'body' => $record->body_id, 'lapel' => $record->id, 'focus' => 'lapel'],
            $record instanceof Sleeve => ['fabric' => $record->fabric_id, 'sleeve' => $record->id, 'focus' => 'sleeve'],
            $record instanceof SidePocket => ['fabric' => $record->fabric_id, 'side_pocket' => $record->id, 'focus' => 'sidePocket'],
            $record instanceof ChestPocket => ['fabric' => $record->fabric_id, 'chest_pocket' => $record->id, 'focus' => 'chestPocket'],
            $record instanceof DefaultLining => ['fabric' => $record->fabric_id, 'body' => Body::query()->where('fabric_id', $record->fabric_id)->where('body_type_id', $record->body_type_id)->value('id'), 'focus' => 'defaultLining'],
            $record instanceof CustomLining => ['lining' => $record->id, 'focus' => 'lining'],
            $record instanceof BodyButton => (function () use ($record) {
                /* Buttons belong to a body style, not a fabric: show them on the first fabric that has that style */
                $body = Body::query()->where('body_type_id', $record->body_type_id)->orderByDesc('status')->orderBy('id')->first();

                return ['fabric' => $body?->fabric_id, 'body' => $body?->id, 'button' => $record->id, 'focus' => 'button'];
            })(),
            default => [],
        };

        return static::getUrl(array_filter($params));
    }

    /** What the designer starts a fabric on: its defaults, else the first of each. */
    private function defaultsFor(?int $fabricId): array
    {
        $first = fn ($query) => $query->orderByDesc('is_default')->orderByDesc('status')->orderBy('sort_order')->orderBy('id')->value('id');

        $bodyId = $first(Body::query()->where('fabric_id', $fabricId));

        $sleeveTypeDefault = SleeveType::defaultId();
        $sleeveId = ($sleeveTypeDefault ? Sleeve::query()->where('fabric_id', $fabricId)->where('sleeve_type_id', $sleeveTypeDefault)->value('id') : null)
            ?? $first(Sleeve::query()->where('fabric_id', $fabricId));

        return [
            'fabric_id' => $fabricId,
            'body_id' => $bodyId,
            'lapel_id' => $this->defaultLapelId($bodyId),
            'sleeve_id' => $sleeveId,
            'side_pocket_id' => $first(SidePocket::query()->where('fabric_id', $fabricId)),
            'chest_pocket_id' => $first(ChestPocket::query()->where('fabric_id', $fabricId)),
            'body_button_id' => $this->defaultButtonId($bodyId),
            'custom_lining_id' => null,
        ];
    }

    private function applyFabric(?int $fabricId, Set $set, Get $get): void
    {
        foreach ($this->defaultsFor($fabricId) as $field => $value) {
            /* The lining is not tied to a fabric, so a chosen one stays */
            if ($field !== 'fabric_id' && $field !== 'custom_lining_id') {
                $set($field, $value);
            }
        }
    }

    private function defaultLapelId(?int $bodyId): ?int
    {
        if (! $bodyId) {
            return null;
        }

        $lapels = Lapel::query()->where('body_id', $bodyId);

        return (clone $lapels)->where('lapel_category_id', LapelCategory::defaultId())->where('lapel_subcategory_id', LapelSubCategory::defaultId())->value('id')
            ?? (clone $lapels)->where('lapel_category_id', LapelCategory::defaultId())->orderBy('id')->value('id')
            ?? $lapels->orderBy('id')->value('id');
    }

    private function defaultButtonId(?int $bodyId): ?int
    {
        $typeId = $bodyId ? Body::query()->whereKey($bodyId)->value('body_type_id') : null;

        return $typeId
            ? BodyButton::query()->where('body_type_id', $typeId)->orderByDesc('is_default')->orderByDesc('status')->orderBy('sort_order')->orderBy('id')->value('id')
            : null;
    }

    /** Switched off in the admin: not shown to customers. Some models cast status, some do not. */
    private static function isHidden(Model $record): bool
    {
        return in_array($record->getAttribute('status'), [false, 0, '0'], true);
    }

    private function editUrl(Model $record): ?string
    {
        $resource = Filament::getModelResource($record);

        return $resource && $resource::hasPage('edit') ? $resource::getUrl('edit', ['record' => $record]) : null;
    }
}
