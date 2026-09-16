<?php

namespace App\Filament\Resources\FabricImages;

use App\Filament\Resources\FabricImages\Pages\CreateFabricImage;
use App\Filament\Resources\FabricImages\Pages\EditFabricImage;
use App\Filament\Resources\FabricImages\Pages\ListFabricImages;
use App\Models\FabricImage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FabricImageResource extends Resource
{
    protected static ?string $model = FabricImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Fabrics';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Fabric pictures';

    protected static ?string $modelLabel = 'fabric picture';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('fabric_id')
                ->relationship('fabric', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('kind')
                ->options(FabricImage::KINDS)
                ->default('preview')
                ->required()
                ->native(false),
            TextInput::make('url')
                ->label('Image URL')
                ->url()
                ->required()
                ->helperText('Bulk upload from Fabrics → Upload pictures: FPI_Fabric Name.png for previews, RL_Fabric Name.png for real-life pictures.')
                ->columnSpanFull(),
            TextInput::make('caption')
                ->maxLength(80)
                ->placeholder('2 Piece Suit')
                ->helperText('Shown under real-life pictures; ignored for previews.'),
            TextInput::make('sort_order')
                ->label('Position')
                ->numeric()
                ->minValue(1)
                ->maxValue(FabricImage::MAX_PER_KIND)
                ->default(1)
                ->helperText('1 shows first. Up to ' . FabricImage::MAX_PER_KIND . ' per kind and fabric.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('fabric.name')
            ->groups([
                Group::make('fabric.name')->label('Fabric')->collapsible(),
                Group::make('kind')->label('Kind'),
            ])
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('url')->label('Picture')->square()->size(64),
                TextColumn::make('fabric.name')->label('Fabric')->searchable()->sortable(),
                TextColumn::make('kind')->badge()->formatStateUsing(fn (string $state) => FabricImage::KINDS[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'real_life' ? 'info' : 'gray'),
                TextColumn::make('caption')->placeholder('—')->searchable(),
                TextColumn::make('sort_order')->label('#')->sortable()->alignCenter(),
                TextColumn::make('url')->label('URL')->limit(40)->copyable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('fabric_id')->label('Fabric')->relationship('fabric', 'name')->searchable()->preload(),
                SelectFilter::make('kind')->options(FabricImage::KINDS),
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
            'index' => ListFabricImages::route('/'),
            'create' => CreateFabricImage::route('/create'),
            'edit' => EditFabricImage::route('/{record}/edit'),
        ];
    }
}
