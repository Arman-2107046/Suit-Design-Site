<?php

namespace App\Filament\Resources\BlogComments;

use App\Filament\Resources\BlogComments\Pages\ListBlogComments;
use App\Models\BlogComment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BlogCommentResource extends Resource
{
    protected static ?string $model = BlogComment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Journal';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Comments';

    protected static ?string $modelLabel = 'comment';

    public static function getNavigationBadge(): ?string
    {
        $pending = BlogComment::where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (BlogComment $record) => $record->email),
                TextColumn::make('body')->label('Comment')->wrap()->limit(160)->searchable(),
                TextColumn::make('post.title')->label('On')->limit(40)->wrap()->url(fn (BlogComment $record) => $record->post ? url("/journal/{$record->post->slug}") : null)->openUrlInNewTab(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => BlogComment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'approved' => 'success', 'hidden' => 'gray', default => 'warning' }),
                TextColumn::make('created_at')->label('Posted')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BlogComment::STATUSES)->default('pending'),
                SelectFilter::make('blog_post_id')->label('Post')->relationship('post', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('approve')->label('Approve')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (BlogComment $record) => $record->status !== 'approved')
                    ->action(fn (BlogComment $record) => $record->update(['status' => 'approved'])),
                Action::make('hide')->label('Hide')->icon(Heroicon::OutlinedEyeSlash)->color('gray')
                    ->visible(fn (BlogComment $record) => $record->status !== 'hidden')
                    ->action(fn (BlogComment $record) => $record->update(['status' => 'hidden'])),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveAll')->label('Approve')->icon(Heroicon::OutlinedCheck)->color('success')
                        ->action(function (Collection $records) {
                            $records->each->update(['status' => 'approved']);
                            Notification::make()->title(count($records) . ' comment(s) approved')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('hideAll')->label('Hide')->icon(Heroicon::OutlinedEyeSlash)
                        ->action(function (Collection $records) {
                            $records->each->update(['status' => 'hidden']);
                            Notification::make()->title(count($records) . ' comment(s) hidden')->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogComments::route('/'),
        ];
    }
}
