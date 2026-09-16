<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\SampleRequest;
use Filament\Widgets\Widget;

class WelcomeBanner extends Widget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    protected function getViewData(): array
    {
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        $pendingOrders = Order::where('status', OrderStatus::Pending)->count();
        $inProduction = Order::whereIn('status', [OrderStatus::Confirmed, OrderStatus::InProduction, OrderStatus::QualityCheck])->count();
        $newMessages = ContactMessage::where('status', 'new')->count();
        $openSamples = SampleRequest::where('status', 'requested')->count();

        $todo = collect([
            $pendingOrders ? [$pendingOrders, 'order' . ($pendingOrders === 1 ? '' : 's') . ' to confirm', '/admin/orders?tableFilters[status][values][0]=pending'] : null,
            $newMessages ? [$newMessages, 'unread message' . ($newMessages === 1 ? '' : 's'), '/admin/contact-messages?tableFilters[status][value]=new'] : null,
            $openSamples ? [$openSamples, 'sample request' . ($openSamples === 1 ? '' : 's') . ' to post', '/admin/sample-requests?tableFilters[status][value]=requested'] : null,
        ])->filter()->values();

        return [
            'greeting' => $greeting,
            'name' => str(auth()->user()?->name ?? 'there')->before(' '),
            'date' => now()->format('l, j F'),
            'todo' => $todo,
            'inProduction' => $inProduction,
        ];
    }
}
