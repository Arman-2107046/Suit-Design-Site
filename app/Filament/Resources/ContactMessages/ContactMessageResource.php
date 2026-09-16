<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;


    protected static ?string $navigationLabel = 'Messages';


    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn (ContactMessage $record) => $record->email),
                TextColumn::make('subject')->searchable()->placeholder('—')->limit(40),
                TextColumn::make('message')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'new' => 'warning', 'replied' => 'success', default => 'gray' }),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactMessage::STATUSES),
            ])
            ->recordActions([
                self::statusAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function statusAction(): Action
    {
        return Action::make('status')
            ->label('Update')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->fillForm(fn (ContactMessage $record) => ['status' => $record->status, 'notes' => $record->notes])
            ->schema([
                Select::make('status')->options(ContactMessage::STATUSES)->required()->native(false),
                Textarea::make('notes')->label('Internal notes')->rows(3),
            ])
            ->action(function (ContactMessage $record, array $data) {
                $record->update($data);
                Notification::make()->title('Message updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->components([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('subject')->placeholder('—')->columnSpanFull(),
                TextEntry::make('message')->columnSpanFull()->prose(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state),
                TextEntry::make('notes')->label('Internal notes')->placeholder('—')->columnSpan(2),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
