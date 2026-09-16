<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/* A simple content page: /p/{slug}. Seeded with About, Perfect Fit Guarantee, Terms and Privacy. */
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'subtitle', 'hero_image', 'hero_image_url', 'body', 'is_published'];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if ($page->isDirty('hero_image')) {
                $previous = $page->getOriginal('hero_image');
                $page->hero_image_url = filled($page->hero_image) ? Storage::disk('cloudinary')->url($page->hero_image) : null;
                if (filled($previous) && $previous !== $page->hero_image) {
                    rescue(fn () => Storage::disk('cloudinary')->delete($previous), report: false);
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
