<?php

namespace App\Filament\Resources\SampleRequests;

use App\Filament\Resources\SampleRequests\Pages\ListSampleRequests;
use App\Filament\Resources\SampleRequests\Pages\ViewSampleRequest;
use App\Models\SampleRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SampleRequestResource extends Resource
{
    protected static ?string $model = SampleRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Sample requests';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $open = SampleRequest::where('status', 'requested')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('shipping.name')->label('Customer')->searchable(query: fn ($query, $search) => $query->where('email', 'like', "%{$search}%")->orWhere('shipping->name', 'like', "%{$search}%"))
                    ->description(fn (SampleRequest $record) => $record->email),
                TextColumn::make('fabrics')->label('Swatches')->formatStateUsing(fn ($state) => is_array($state) ? collect($state)->pluck('name')->implode(', ') : '')->wrap(),
                TextColumn::make('shipping.country')->label('Country'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => SampleRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'requested' => 'warning', 'sent' => 'success', default => 'gray' }),
                TextColumn::make('created_at')->label('Requested')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SampleRequest::STATUSES),
            ])
            ->recordActions([
                self::statusAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markSent')
                        ->label('Mark as sent')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->action(function (Collection $records) {
                            $records->each->update(['status' => 'sent']);
                            Notification::make()->title(count($records) . ' request(s) marked as sent')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function statusAction(): Action
    {
        return Action::make('status')
            ->label('Update status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->fillForm(fn (SampleRequest $record) => ['status' => $record->status])
            ->schema([Select::make('status')->options(SampleRequest::STATUSES)->required()->native(false)])
            ->action(function (SampleRequest $record, array $data) {
                $record->update($data);
                Notification::make()->title('Sample request updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->components([
                Section::make('Request')->columnSpan(2)->columns(3)->components([
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => SampleRequest::STATUSES[$state] ?? $state),
                    TextEntry::make('created_at')->label('Requested')->dateTime(),
                    TextEntry::make('sent_at')->dateTime()->placeholder('—'),
                    TextEntry::make('notes')->placeholder('—')->columnSpan(2),
                ]),
                Section::make('Delivery')->components([
                    TextEntry::make('shipping.name')->label('Name'),
                    TextEntry::make('shipping.phone')->label('Phone')->placeholder('—'),
                    TextEntry::make('shipping.address')->label('Address')
                        ->formatStateUsing(fn (SampleRequest $record) => collect([$record->shipping['address'] ?? null, $record->shipping['address2'] ?? null])->filter()->implode(', ')),
                    TextEntry::make('shipping.city')->label('City / Postcode')
                        ->formatStateUsing(fn (SampleRequest $record) => trim(($record->shipping['city'] ?? '') . ' ' . ($record->shipping['postcode'] ?? ''))),
                    TextEntry::make('shipping.country')->label('Country'),
                ]),
            ]),
            Section::make('Swatches')->components([
                RepeatableEntry::make('fabrics')->hiddenLabel()->columns(4)->components([
                    ImageEntry::make('image')->hiddenLabel()->square()->size(56),
                    TextEntry::make('name')->hiddenLabel()->weight('medium')->columnSpan(3),
                ]),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSampleRequests::route('/'),
            'view' => ViewSampleRequest::route('/{record}'),
        ];
    }
}
