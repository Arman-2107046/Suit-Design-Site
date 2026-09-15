<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    public const IMAGES = ['hero', 'designer', 'planet', 'tailor'];

    public const SOCIALS = ['instagram', 'facebook', 'x', 'pinterest', 'tiktok'];

    /* Ordered logo lists, each entry {path, url}. */
    public const LOGO_LISTS = ['payment_logos', 'shipping_logos'];

    protected $fillable = [
        'hero_image',
        'hero_image_url',
        'suits_image',
        'suits_image_url',
        'designer_image',
        'designer_image_url',
        'designer_video_url',
        'planet_image',
        'planet_image_url',
        'tailor_image',
        'tailor_image_url',
        'instagram_url',
        'facebook_url',
        'x_url',
        'pinterest_url',
        'tiktok_url',
        'payment_logos',
        'shipping_logos',
    ];

    protected $casts = [
        'payment_logos' => 'array',
        'shipping_logos' => 'array',
    ];

    /* Single-row settings: there is only ever one homepage. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /** @return array{images: array<string, ?string>, video: ?string, socials: array<string, string>, payment_logos: string[], shipping_logos: string[]} */
    public function toHomepageProps(): array
    {
        $images = [];

        foreach (self::IMAGES as $image) {
            $images[$image] = $this->{"{$image}_image_url"};
        }

        $socials = [];

        foreach (self::SOCIALS as $network) {
            if (filled($this->{"{$network}_url"})) {
                $socials[$network] = $this->{"{$network}_url"};
            }
        }

        return [
            'images' => $images,
            'video' => $this->designer_video_url,
            'socials' => (object) $socials,
            'payment_logos' => array_column($this->payment_logos ?? [], 'url'),
            'shipping_logos' => array_column($this->shipping_logos ?? [], 'url'),
        ];
    }
}
