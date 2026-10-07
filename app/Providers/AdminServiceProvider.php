<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Admin;
use App\Policies\ActivityPolicy;
use App\Policies\AdminPolicy;
use App\Policies\StaffPolicy;
use App\Services\Activity\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/*
 * Who may do what in the admin, and the activity log that records it.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        /* Every other record follows one rule: admins edit, super admins also delete. */
        Gate::guessPolicyNamesUsing(fn () => StaffPolicy::class);

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($event) {
                ActivityLogger::record($event, $payload[0]);
            });
        }

        Event::listen(function (Login $event) {
            if ($event->guard !== 'admin' || ! $event->user instanceof Admin) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            ActivityLogger::log('login', admin: $event->user);
        });

        Event::listen(function (Logout $event) {
            if ($event->guard === 'admin' && $event->user instanceof Admin) {
                ActivityLogger::log('logout', admin: $event->user);
            }
        });

        Event::listen(function (Failed $event) {
            if ($event->guard !== 'admin') {
                return;
            }

            /* The account someone tried to get into, if it exists — not who did it, which is unknown. */
            ActivityLogger::log(
                'login_failed',
                $event->user instanceof Admin ? $event->user : null,
                label: (string) ($event->credentials['email'] ?? 'unknown'),
            );
        });
    }
}
