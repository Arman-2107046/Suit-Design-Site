<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\SuperAdminOnly;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Order;
use App\Services\Payments\OrderPayments;
use App\Services\Payments\StripeGateway;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/*
 * Card payments through Stripe: whether they are on, test or live, and the
 * exact steps and webhook address to set them up. The keys themselves stay
 * in .env on the server, never in the database or this page.
 */
class StripePayments extends Page
{
    use SuperAdminOnly;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Stripe';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Stripe payments';

    protected static ?string $slug = 'stripe';

    protected ?string $subheading = 'Take card payments at checkout. Customers pay on Stripe’s secure page; card details never touch this site.';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.stripe.setup')->viewData(fn () => $this->viewData()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test connection')
                ->icon(Heroicon::OutlinedSignal)
                ->disabled(fn () => ! app(StripeGateway::class)->enabled())
                ->tooltip(fn () => app(StripeGateway::class)->enabled() ? null : 'Add the keys to .env first')
                ->action(function (StripeGateway $stripe) {
                    try {
                        $stripe->check();
                        Notification::make()->title('Connected to Stripe')->body('The secret key works ('.$stripe->mode().' mode).')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Could not connect to Stripe')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $stripe = app(StripeGateway::class);
        $card = Order::query()->where('payment_method', OrderPayments::CARD);

        return [
            'enabled' => $stripe->enabled(),
            'mode' => $stripe->mode(),
            'hasKey' => filled(config('services.stripe.key')),
            'hasSecret' => filled(config('services.stripe.secret')),
            'hasWebhookSecret' => filled(config('services.stripe.webhook_secret')),
            'webhookUrl' => route('webhooks.stripe'),
            'isPublic' => ! preg_match('#^https?://(localhost|127\.|[^/]+\.test(?:[:/]|$))#i', route('webhooks.stripe')),
            'events' => StripeWebhookController::EVENTS,
            'paidCount' => (clone $card)->where('payment_status', 'paid')->count(),
            'paidTotal' => (float) (clone $card)->where('payment_status', 'paid')->sum('total'),
            'awaiting' => (clone $card)->whereIn('payment_status', ['pending', 'failed'])->count(),
            'recent' => (clone $card)->latest('id')->limit(8)->get(['id', 'number', 'email', 'total', 'currency', 'payment_status', 'stripe_payment_intent_id', 'created_at']),
            'stripe' => $stripe,
        ];
    }
}
