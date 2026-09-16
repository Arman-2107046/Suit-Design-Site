<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'FAQs';

    protected static ?string $modelLabel = 'FAQ';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('category')->required()->maxLength(80)->default('General')->datalist(['General', 'Ordering', 'Fit & measurements', 'Delivery', 'Returns & alterations', 'Fabrics'])
                ->helperText('Questions are grouped by category on the page.'),
            Toggle::make('is_published')->label('Published')->default(true),
            TextInput::make('question')->required()->maxLength(255)->columnSpanFull(),
            RichEditor::make('answer')->required()->columnSpanFull()
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->defaultGroup('category')
            ->groups([Group::make('category')->collapsible()])
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('question')->searchable()->weight('medium')->wrap(),
                TextColumn::make('category')->badge()->color('gray'),
                IconColumn::make('is_published')->label('Published')->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_published')->label('Published')->options([1 => 'Published', 0 => 'Draft']),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
