<?php

namespace App\Filament\Resources\BlogPosts;

use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Journal';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Posts';

    protected static ?string $modelLabel = 'post';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->components([
                Section::make()->components([
                    TextInput::make('title')->required()->maxLength(160)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => filled($state) && blank($get('slug')) ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('slug')->required()->maxLength(160)->unique(ignoreRecord: true)->prefix('/journal/'),
                    Textarea::make('excerpt')->rows(3)->maxLength(300)->helperText('One or two sentences for cards, search results and the RSS feed. Left empty, the first lines of the story are used.'),
                ]),
                Section::make('Story')->components([
                    RichEditor::make('body')->hiddenLabel()->required()
                        ->fileAttachmentsDisk('cloudinary')->fileAttachmentsDirectory('journal')
                        ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'underline', 'strike', 'link', 'blockquote', 'bulletList', 'orderedList', 'attachFiles', 'undo', 'redo']),
                ]),
                Section::make('Search preview')->description('How the story appears on Google and when shared. Leave empty to use the title and excerpt.')->columns(2)->components([
                    TextInput::make('seo_title')->label('SEO title')->maxLength(70),
                    TextInput::make('seo_description')->label('SEO description')->maxLength(160),
                ]),
            ]),

            Grid::make(1)->columnSpan(1)->components([
                Section::make('Publishing')->components([
                    Select::make('status')->options(BlogPost::STATUSES)->default('draft')->required()->native(false),
                    DateTimePicker::make('published_at')->label('Publish at')->seconds(false)->helperText('Leave empty to publish immediately when the status is set to Published. A future date schedules it.'),
                    Toggle::make('is_featured')->label('Featured story')->helperText('Shown large at the top of the journal.'),
                    Select::make('user_id')->label('Author')->relationship('author', 'name')->default(fn () => auth()->id())->searchable()->preload()->required(),
                ]),
                Section::make('Cover')->components([
                    FileUpload::make('cover_image')->hiddenLabel()->disk('cloudinary')->directory('journal')->image()->imageEditor()->imageEditorAspectRatios(['16:9', '3:2', '4:5'])->maxSize(10240)->fetchFileInformation(false)
                        ->helperText('Landscape, at least 2000px wide.'),
                    TextInput::make('cover_caption')->label('Caption / credit')->maxLength(160),
                ]),
                Section::make('Organise')->components([
                    Select::make('blog_category_id')->label('Category')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([TextInput::make('name')->required()->maxLength(80)]),
                    TagsInput::make('tags')->suggestions(['Style', 'Fabric', 'Fit', 'Weddings', 'Care', 'Behind the seams', 'Lifestyle']),
                    Select::make('fabrics')->label('Shop the cloths')->relationship('fabrics', 'name')->multiple()->searchable()->preload()
                        ->helperText('Fabrics featured in the story appear as a "Design in this cloth" strip at the end.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image_url')->label('')->square()->size(48),
                TextColumn::make('title')->searchable()->weight('medium')->wrap()->description(fn (BlogPost $record) => $record->category?->name),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => BlogPost::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('is_featured')->label('Featured')->boolean()->alignCenter(),
                TextColumn::make('published_at')->label('Published')->dateTime('j M Y, H:i')->sortable()->placeholder('—'),
                TextColumn::make('views')->alignCenter()->sortable()->toggleable(),
                TextColumn::make('likes_count')->counts('likes')->label('Likes')->alignCenter()->toggleable(),
                TextColumn::make('comments_count')->counts('comments')->label('Comments')->alignCenter()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BlogPost::STATUSES),
                SelectFilter::make('blog_category_id')->label('Category')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('open')->label('View')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (BlogPost $record) => url("/journal/{$record->slug}"))->openUrlInNewTab()
                    ->visible(fn (BlogPost $record) => $record->status === 'published'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')->label('Publish')->icon(Heroicon::OutlinedCheck)
                        ->action(function (Collection $records) {
                            $records->each(fn (BlogPost $p) => $p->update(['status' => 'published']));
                            Notification::make()->title(count($records) . ' post(s) published')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
