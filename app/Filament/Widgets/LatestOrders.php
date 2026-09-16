<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Latest orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->withCount('items')->latest())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('number')->label('Order')->weight('medium'),
                TextColumn::make('shipping.name')->label('Customer')->description(fn (Order $record) => $record->email),
                TextColumn::make('items_count')->label('Suits')->alignCenter(),
                TextColumn::make('total')->money('USD'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->label('Placed')->since(),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([OrderResource::statusAction()]);
    }
}
