<?php

namespace App\Filament\Resources\FabricInfos;

use App\Filament\Resources\FabricInfos\Pages\CreateFabricInfo;
use App\Filament\Resources\FabricInfos\Pages\EditFabricInfo;
use App\Filament\Resources\FabricInfos\Pages\ListFabricInfos;
use App\Models\FabricInfo;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FabricInfoResource extends Resource
{
    protected static ?string $model = FabricInfo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $navigationLabel = 'Fabric info';

    protected static ?string $modelLabel = 'fabric info';

    public static function form(Schema $schema): Schema
    {
        $columnHelp = new HtmlString(
            'Comma-separated <code>Label: value</code> pairs. Add a note in brackets to get an (i) that opens it large: '
            . '<code>Weave: Twill(Twill is a diagonal weave that resists wear.)</code>. '
            . 'Commas inside brackets are kept, and a chunk without a colon continues the previous value, so <code>Occasion: Business, Casual</code> stays one entry.'
        );

        return $schema
            ->columns(1)
            ->components([
                Section::make('Fabric')
                    ->columns(3)
                    ->components([
                        Select::make('fabric_id')
                            ->label('Fabric name')
                            ->relationship('fabric', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('Giuliano')
                            ->helperText('Shown large on the card.')
                            ->columnSpan(2),
                        Textarea::make('description')
                            ->rows(2)
                            ->placeholder('100% wool from the prestigious Italian brand Loro Piana…')
                            ->helperText('Optional. Appears under the title once "More info" is opened.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Badges')
                    ->description('Up to ' . FabricInfo::MAX_BADGES . ' icon + name pairs. The name is taken from the icon\'s filename — "Pure wool.png" becomes "Pure wool" — and can be edited. Drag to reorder.')
                    ->components([
                        Repeater::make('badges')
                            ->hiddenLabel()
                            ->maxItems(FabricInfo::MAX_BADGES)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Add badge')
                            ->itemLabel(fn (array $state) => $state['name'] ?: 'Badge')
                            ->columns(3)
                            ->schema([
                                FileUpload::make('icon')
                                    ->label('Icon')
                                    ->disk('cloudinary')
                                    ->directory('fabric-icons')
                                    ->image()
                                    ->maxSize(1024)
                                    ->fetchFileInformation(false)
                                    ->imagePreviewHeight('80')
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state, ?string $old) {
                                        if ($state instanceof TemporaryUploadedFile) {
                                            $name = pathinfo($state->getClientOriginalName(), PATHINFO_FILENAME);
                                            $set('name', ucfirst(trim(preg_replace('/[_-]+/', ' ', $name))));
                                        }
                                    }),
                                TextInput::make('name')
                                    ->label('Name')
                                    ->maxLength(60)
                                    ->placeholder('Pure wool')
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Details')
                    ->description('Four columns, hidden until the customer clicks "More info".')
                    ->columns(2)
                    ->components([
                        Textarea::make('column_1')->label('Column 1')->rows(4)->placeholder('Tone: Black, Pattern: Checked, Weave: Serge(A twill weave with a pronounced diagonal.), Brand: Loro Piana')->helperText($columnHelp),
                        Textarea::make('column_2')->label('Column 2')->rows(4)->placeholder('Category: Premium(Fine Italian mills.), Seasonality: Year round, Suggested occasion: Business, Casual, Celebration'),
                        Textarea::make('column_3')->label('Column 3')->rows(4)->placeholder('Weight: Medium (280 gr/m²), Composition: Pure wool (100% Wool)'),
                        Textarea::make('column_4')->label('Column 4')->rows(4)->placeholder('Finish: Sheen, Opacity: Very opaque'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('fabric.name')->label('Fabric')->searchable()->sortable()->weight('medium'),
                TextColumn::make('title')->searchable(),
                TextColumn::make('badges')->label('Badges')->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)->alignCenter(),
                TextColumn::make('column_1')->label('Details')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('fabric_id')->label('Fabric')->relationship('fabric', 'name')->searchable()->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListFabricInfos::route('/'),
            'create' => CreateFabricInfo::route('/create'),
            'edit' => EditFabricInfo::route('/{record}/edit'),
        ];
    }
}
