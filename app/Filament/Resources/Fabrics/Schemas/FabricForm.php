<?php

namespace App\Filament\Resources\Fabrics\Schemas;

use App\Models\FabricImage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class FabricForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Fabric')
                    ->columns(3)
                    ->components([
                        TextInput::make('name')->required()->columnSpan(2),
                        TextInput::make('price')->required()->numeric()->prefix('$'),
                        TextInput::make('image')->label('Swatch image URL')->url()->required()->columnSpan(3),
                        Toggle::make('is_default')->label('Default fabric')->default(false),
                        Toggle::make('is_new')->label('Show "New" badge')->default(false)->helperText('The info card (title, badges, details) is managed under Fabric info.'),
                        Toggle::make('status')->label('Active')->default(true),
                    ]),

                Section::make('Pictures')
                    ->description(new HtmlString('Up to 10 per kind. Bulk upload from the Fabrics list with filenames like <code>FPI_Fabric Name_1.png</code> (preview) or <code>RL_Fabric Name_1.png</code> (real life). Drag to reorder.'))
                    ->components([
                        Repeater::make('images')
                            ->relationship()
                            ->hiddenLabel()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->collapsible()
                            ->collapsed()
                            ->defaultItems(0)
                            ->addActionLabel('Add picture by URL')
                            ->itemLabel(fn (array $state) => (FabricImage::KINDS[$state['kind'] ?? 'preview'] ?? 'Picture') . ' · ' . basename((string) ($state['url'] ?? '')))
                            ->columns(4)
                            ->schema([
                                Select::make('kind')->options(FabricImage::KINDS)->default('preview')->required()->native(false),
                                TextInput::make('url')->label('Image URL')->url()->required()->columnSpan(2),
                                TextInput::make('caption')->maxLength(80)->placeholder('2 Piece Suit')->helperText('Shown under real-life pictures.'),
                            ]),
                    ]),
            ]);
    }

}
