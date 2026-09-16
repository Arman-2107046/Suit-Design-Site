<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\BodyEstimator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;



    public static function getNavigationBadge(): ?string
    {
        $open = Order::where('status', OrderStatus::Pending)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Order')->searchable()->weight('medium')->copyable(),
                TextColumn::make('shipping.name')->label('Customer')->searchable(query: fn ($query, $search) => $query->where('email', 'like', "%{$search}%")->orWhere('shipping->name', 'like', "%{$search}%"))
                    ->description(fn (Order $record) => $record->email),
                TextColumn::make('items_count')->counts('items')->label('Suits')->alignCenter(),
                TextColumn::make('total')->money('USD')->sortable(),
                TextColumn::make('payment_method')->label('Payment')->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst())
                    ->description(fn (Order $record) => str($record->payment_status)->ucfirst()),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->label('Placed')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class)->multiple(),
                SelectFilter::make('payment_status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'refunded' => 'Refunded']),
            ])
            ->recordActions([
                self::statusAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markStatus')
                        ->label('Change status')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->schema([Select::make('status')->options(OrderStatus::class)->required()])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['status' => $data['status']]);
                            Notification::make()->title(count($records) . ' order(s) updated')->success()->send();
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
            ->modalHeading(fn (Order $record) => "Order {$record->number}")
            ->fillForm(fn (Order $record) => ['status' => $record->status, 'payment_status' => $record->payment_status, 'notes' => $record->notes])
            ->schema([
                Select::make('status')->options(OrderStatus::class)->required()->native(false),
                Select::make('payment_status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'refunded' => 'Refunded'])->required()->native(false),
                Textarea::make('notes')->label('Internal notes')->rows(3),
            ])
            ->action(function (Order $record, array $data) {
                $record->update([
                    ...$data,
                    'paid_at' => $data['payment_status'] === 'paid' ? ($record->paid_at ?? now()) : null,
                ]);
                Notification::make()->title("Order {$record->number} updated")->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->components([
                Section::make('Order')->columnSpan(2)->columns(3)->components([
                    TextEntry::make('number')->label('Number')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('created_at')->label('Placed')->dateTime(),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('payment_method')->label('Payment')->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->ucfirst()),
                    TextEntry::make('payment_status')->badge()->color(fn (string $state) => match ($state) { 'paid' => 'success', 'refunded' => 'danger', default => 'gray' }),
                    TextEntry::make('total')->money('USD')->weight('bold'),
                    TextEntry::make('shipped_at')->dateTime()->placeholder('—'),
                    TextEntry::make('delivered_at')->dateTime()->placeholder('—'),
                    TextEntry::make('notes')->label('Internal notes')->columnSpanFull()->placeholder('—'),
                ]),

                Section::make('Delivery')->components([
                    TextEntry::make('shipping.name')->label('Name'),
                    TextEntry::make('shipping.phone')->label('Phone'),
                    TextEntry::make('shipping.address')->label('Address')
                        ->formatStateUsing(fn (Order $record) => collect([$record->shipping['address'] ?? null, $record->shipping['address2'] ?? null])->filter()->implode(', ')),
                    TextEntry::make('shipping.city')->label('City / Postcode')
                        ->formatStateUsing(fn (Order $record) => trim(($record->shipping['city'] ?? '') . ' ' . ($record->shipping['postcode'] ?? ''))),
                    TextEntry::make('shipping.country')->label('Country'),
                ]),
            ]),

            Section::make('Suits')->components([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->components([
                    ImageEntry::make('fabric_image')->label('Cloth')->square()->size(72),
                    TextEntry::make('fabric_name')->label('Fabric')->weight('medium'),
                    TextEntry::make('price')->money('USD'),
                    TextEntry::make('quantity')->label('Qty'),
                    // Filament hands each array element to the formatter in turn.
                    TextEntry::make('summary')->label('Design')->columnSpanFull()->listWithLineBreaks()
                        ->formatStateUsing(fn ($state) => is_array($state) ? "{$state['label']}: {$state['value']}" : $state),
                ]),
            ]),

            Section::make('Body profile')->columns(4)->components([
                TextEntry::make('body_profile.name')->label('Profile'),
                TextEntry::make('body_profile.height_cm')->label('Height')->suffix(' cm'),
                TextEntry::make('body_profile.weight_kg')->label('Weight')->suffix(' kg'),
                TextEntry::make('body_profile.age')->label('Age')->suffix(' years'),
                ...collect(BodyEstimator::FIELDS)->map(fn ($label, $key) => TextEntry::make("body_profile.measurements.{$key}")->label($label)->suffix(' cm'))->values()->all(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
