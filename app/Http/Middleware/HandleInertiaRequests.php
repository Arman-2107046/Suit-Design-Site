<?php

namespace App\Http\Middleware;

use App\Models\HomepageSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => fn () => [
                'profile' => $request->session()->get('profile'),
            ],
            'brand' => fn () => [
                'heroImage' => $request->routeIs('login', 'register', 'password.*', 'verification.*')
                    ? HomepageSetting::current()->hero_image_url
                    : null,
            ],
        ];
    }
}
