<?php

namespace App\Support;

/*
 * A small version of a stored image, for places like the admin dashboard that
 * would otherwise pull a 2-4 MB original into a 48px swatch. The PHP twin of
 * resources/js/lib/media.js: Cloudflare images get a flexible variant; old
 * Cloudinary addresses get a transformation; anything else is left alone.
 */
final class ImageUrl
{
    public static function sized(?string $url, int $width): ?string
    {
        if (! $url) {
            return $url;
        }

        if (preg_match('~^(https://imagedelivery\.net/[^/]+/.+)/[^/]+$~', $url, $m)) {
            return "{$m[1]}/w={$width},fit=scale-down,f=auto";
        }

        if (preg_match('~^(https?://res\.cloudinary\.com/[^/]+/image/upload/)(v\d+/.+)$~', $url, $m)) {
            return "{$m[1]}f_auto,q_auto,w_{$width}/{$m[2]}";
        }

        return $url;
    }
}
