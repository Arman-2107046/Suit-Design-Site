<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class SupportInbox extends TableWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Unread messages';

    public function table(Table $table): Table
    {
        return $table
            ->query(ContactMessage::query()->where('status', 'new')->latest())
            ->paginated([5])
            ->emptyStateHeading('Inbox zero')
            ->emptyStateDescription('No unread messages from the contact form.')
            ->columns([
                TextColumn::make('name')->weight('medium')->description(fn (ContactMessage $record) => $record->email),
                TextColumn::make('subject')->placeholder('—')->limit(40),
                TextColumn::make('message')->limit(80)->wrap(),
                TextColumn::make('created_at')->label('Received')->since(),
            ])
            ->recordUrl(fn (ContactMessage $record) => ContactMessageResource::getUrl('view', ['record' => $record]))
            ->recordActions([ContactMessageResource::statusAction()]);
    }
}
