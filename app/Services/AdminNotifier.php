<?php

namespace App\Services;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SampleRequests\SampleRequestResource;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\SampleRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

/*
 * What lands in the admin's bell. Everything that needs someone's attention
 * goes to every administrator; a failure here is reported, never allowed to
 * break the checkout or form that triggered it.
 */
class AdminNotifier
{
    public static function orderPlaced(Order $order): void
    {
        $paidByCard = $order->payment_method === 'card';

        self::send(
            Notification::make()
                ->title($paidByCard ? "New order {$order->number}, paid by card" : "New order {$order->number}")
                ->body(self::who($order).' · $'.number_format((float) $order->total, 2).' · '.self::suits($order))
                ->icon('heroicon-o-shopping-bag')
                ->iconColor('success')
                ->actions([self::open(OrderResource::getUrl('view', ['record' => $order]), 'View order')]),
        );
    }

    public static function cardPaymentFailed(Order $order): void
    {
        self::send(
            Notification::make()
                ->title("Card payment didn't go through: {$order->number}")
                ->body(self::who($order).' · $'.number_format((float) $order->total, 2).'. The order is waiting for payment; it may be worth getting in touch.')
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
                ->actions([self::open(OrderResource::getUrl('view', ['record' => $order]), 'View order')]),
        );
    }

    public static function messageReceived(ContactMessage $message): void
    {
        self::send(
            Notification::make()
                ->title("New message from {$message->name}")
                ->body($message->subject ?: str($message->message)->limit(90)->toString())
                ->icon('heroicon-o-envelope')
                ->iconColor('info')
                ->actions([self::open(ContactMessageResource::getUrl('view', ['record' => $message]), 'Read it')]),
        );
    }

    public static function samplesRequested(SampleRequest $sample): void
    {
        $count = count($sample->fabrics ?? []);

        self::send(
            Notification::make()
                ->title('Swatches requested by '.($sample->shipping['name'] ?? $sample->email))
                ->body($count.' '.str('fabric')->plural($count).' to post to '.($sample->shipping['city'] ?? 'them').', '.($sample->shipping['country'] ?? ''))
                ->icon('heroicon-o-swatch')
                ->iconColor('warning')
                ->actions([self::open(SampleRequestResource::getUrl('view', ['record' => $sample]), 'View request')]),
        );
    }

    public static function articleImported(BlogPost $post): void
    {
        $state = match (true) {
            $post->status !== 'published' => 'Saved as a draft for you to review.',
            $post->published_at?->isFuture() => 'Scheduled for '.$post->published_at->format('j M, H:i').'.',
            default => 'Now live in the journal.',
        };

        self::send(
            Notification::make()
                ->title("RankYak article: {$post->title}")
                ->body($state)
                ->icon('heroicon-o-bolt')
                ->iconColor('primary')
                ->actions([self::open(BlogPostResource::getUrl('edit', ['record' => $post]), 'Open post')]),
        );
    }

    public static function brokenImages(int $broken, int $new): void
    {
        self::send(
            Notification::make()
                ->title($new === $broken
                    ? "{$broken} ".str('picture')->plural($broken).' no longer '.($broken === 1 ? 'loads' : 'load')
                    : "{$new} more ".str('picture')->plural($new).' stopped loading')
                ->body("{$broken} broken in all. Customers may see gaps where they should be.")
                ->icon('heroicon-o-photo')
                ->iconColor('danger')
                ->actions([self::open(\App\Filament\Pages\ImageHealth::getUrl(), 'See which')]),
        );
    }

    private static function send(Notification $notification): void
    {
        try {
            $admins = Admin::query()->get();

            if ($admins->isNotEmpty()) {
                $notification->sendToDatabase($admins);   // the bell polls, so no broadcasting server is needed
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function open(string $url, string $label): Action
    {
        return Action::make('open')->label($label)->url($url)->markAsRead()->button()->size('sm');
    }

    private static function who(Order $order): string
    {
        return $order->shipping['name'] ?? $order->email;
    }

    private static function suits(Order $order): string
    {
        $count = (int) $order->items()->sum('quantity');

        return $count.' '.str('suit')->plural($count);
    }
}
