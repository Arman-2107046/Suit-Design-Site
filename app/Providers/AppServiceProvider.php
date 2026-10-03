<?php

namespace App\Providers;

use App\Filesystem\CloudflareImagesAdapter;
use App\Services\Cloudflare\CloudflareImages;
use App\Services\Cloudflare\CloudflareStream;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CloudflareImages::class, fn () => CloudflareImages::fromConfig());
        $this->app->singleton(CloudflareStream::class, fn () => CloudflareStream::fromConfig());
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

        /* disk('cloudflare') — every image the admin uploads through a form. */
        Storage::extend('cloudflare-images', function ($app, array $config) {
            $adapter = new CloudflareImagesAdapter($app->make(CloudflareImages::class));

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
