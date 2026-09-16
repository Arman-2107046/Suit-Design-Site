<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Pages';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(3)->components([
                TextInput::make('title')->required()->maxLength(160)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, ?string $old, $get) => filled($state) && blank($get('slug')) ? $set('slug', Str::slug($state)) : null)
                    ->columnSpan(2),
                TextInput::make('slug')->required()->maxLength(80)->unique(ignoreRecord: true)->prefix('/p/')->helperText('Lowercase letters, numbers and dashes.'),
                TextInput::make('subtitle')->maxLength(255)->columnSpan(2),
                Toggle::make('is_published')->label('Published')->default(true)->inline(false),
                FileUpload::make('hero_image')->label('Hero photo')->disk('cloudinary')->directory('pages')->image()->imageEditor()->maxSize(10240)->fetchFileInformation(false)
                    ->helperText('Optional. Wide photo shown above the text.')->columnSpanFull(),
            ]),
            Section::make('Body')->components([
                RichEditor::make('body')->hiddenLabel()->required()
                    ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->searchable()->weight('medium'),
                TextColumn::make('slug')->prefix('/p/')->copyable()->color('gray'),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')->label('View')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (Page $p) => url("/p/{$p->slug}"))->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
