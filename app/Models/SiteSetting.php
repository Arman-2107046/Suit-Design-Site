<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/* Single-row settings for the support pages (contact, samples, tracking). */
class SiteSetting extends Model
{
    public const IMAGES = ['contact', 'samples', 'track'];

    protected $fillable = [
        'contact_email', 'contact_phone', 'contact_address', 'contact_hours', 'contact_intro',
        'contact_image', 'contact_image_url',
        'samples_intro', 'samples_max', 'samples_image', 'samples_image_url',
        'track_image', 'track_image_url',
        'notify_email',
    ];

    protected static function booted(): void
    {
        // Resolve public URLs once, when an image changes; drop the old asset.
        static::saving(function (SiteSetting $settings) {
            foreach (self::IMAGES as $image) {
                if (! $settings->isDirty("{$image}_image")) {
                    continue;
                }
                $path = $settings->{"{$image}_image"};
                $previous = $settings->getOriginal("{$image}_image");
                $settings->{"{$image}_image_url"} = filled($path) ? Storage::disk('cloudinary')->url($path) : null;
                if (filled($previous) && $previous !== $path) {
                    rescue(fn () => Storage::disk('cloudinary')->delete($previous), report: false);
                }
            }
        });
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /** Address the admin wants notified about messages and sample requests. */
    public function notifyAddress(): ?string
    {
        return $this->notify_email ?: $this->contact_email ?: null;
    }

    /** @return array<string, mixed> */
    public function toContactArray(): array
    {
        return [
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
            'address' => $this->contact_address,
            'hours' => $this->contact_hours,
            'intro' => $this->contact_intro,
            'image' => $this->contact_image_url,
        ];
    }
}
