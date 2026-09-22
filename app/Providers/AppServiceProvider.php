<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /*
         * `npm run dev` writes a file naming the dev server, and Laravel serves
         * assets from that address for as long as the file exists — it outlives
         * the process that wrote it. Kept outside public/ so a deploy cannot
         * carry it into the web root, and ignored entirely off a local machine,
         * where a stale copy would point every asset at localhost.
         */
        Vite::useHotFile(storage_path(
            $this->app->environment('local') ? 'vite.hot' : 'vite.hot.ignored'
        ));
    }
}
