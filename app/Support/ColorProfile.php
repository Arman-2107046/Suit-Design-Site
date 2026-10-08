<?php

namespace App\Support;

/*
 * Turning Adobe RGB images into sRGB ones, the colour space the web assumes.
 *
 * The renders are exported from Photoshop documents set up in Adobe RGB
 * (1998). Some exports carry that profile and some leave it out, with only a
 * note in EXIF/XMP that the colour space is "uncalibrated" (65535) —
 * Photoshop's way of saying "not sRGB". A browser that finds no profile has to
 * assume sRGB, and Adobe RGB numbers read as sRGB come out flat and washed out.
 *
 * So such a file has its colours converted to sRGB (the same maths the
 * designer in Welcome.jsx uses) and an sRGB profile embedded in place of the
 * Adobe one. Files already in sRGB, or in some other declared colour space,
 * are left exactly as they are. The bulk uploader does the same in the
 * browser (partials/bulk-upload-dock), with the profile bytes made here.
 */
class ColorProfile
{
    public const ADOBE_RGB = 'adobe-rgb';

    public const SRGB = 'srgb';

    /** A profile for some other space (Display P3, ProPhoto…): left alone. */
    public const OTHER = 'other';

    /** No profile and no note either way. */
    public const UNKNOWN = 'unknown';

    public const DESCRIPTION = 'sRGB IEC61966-2.1';

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /** Adobe RGB (1998)'s tone curve: a pure power of 563/256 (≈ 2.2). */
    private const ADOBE_RGB_GAMMA = 563 / 256;

    /**
     * The file in sRGB with an sRGB profile, or exactly as given when it is
     * already sRGB, is in another declared space, is not a PNG or JPEG, or
     * cannot be decoded. $unmarkedIsAdobeRgb treats a file that says nothing
     * about its colours as Adobe RGB, which holds for the suit renders.
     */
    public static function toSrgb(string $bytes, bool $unmarkedIsAdobeRgb = false): string
    {
        $space = self::colourSpace($bytes);

        if ($space === self::ADOBE_RGB || ($unmarkedIsAdobeRgb && $space === self::UNKNOWN)) {
            return self::convert($bytes) ?? $bytes;
        }

        return $bytes;
    }

    /** ADOBE_RGB, SRGB, OTHER or UNKNOWN for a PNG or JPEG; null for anything else. */
    public static function colourSpace(string $bytes): ?string
    {
        if (str_starts_with($bytes, self::PNG_SIGNATURE)) {
            [$icc, $srgbChunk, $metadata] = self::readPng($bytes);
        } elseif (str_starts_with($bytes, "\xFF\xD8")) {
            [$icc, $metadata] = self::readJpeg($bytes);
            $srgbChunk = false;
        } else {
            return null;
        }

        if ($icc !== null) {
            return self::profileSpace($icc);
        }

        if ($srgbChunk) {
            return self::SRGB;
        }

        return match (self::exifColourSpace($metadata)) {
            65535 => self::ADOBE_RGB,
            1 => self::SRGB,
            default => self::UNKNOWN,
        };
    }

    /**
     * An ICC v2 display profile for sRGB IEC61966-2.1: the standard D50-adapted
     * primaries, D65 white and the sRGB tone curve as a 1024-point table.
     */
    public static function srgb(): string
    {
        static $profile;

        if ($profile !== null) {
            return $profile;
        }

        $xyz = fn (float ...$v) => 'XYZ '.str_repeat("\0", 4).implode('', array_map(
            fn ($n) => pack('N', (int) round($n * 65536)), $v
        ));

        $curve = '';
        for ($i = 0; $i < 1024; $i++) {
            $v = $i / 1023;
            $curve .= pack('n', (int) round(($v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4) * 65535));
        }

        $tags = [
            'desc' => 'desc'.str_repeat("\0", 4)
                .pack('N', strlen(self::DESCRIPTION) + 1).self::DESCRIPTION."\0"
                .pack('NN', 0, 0)            // no Unicode description
                .pack('nC', 0, 0).str_repeat("\0", 67), // no ScriptCode description
            'cprt' => 'text'.str_repeat("\0", 4)."No copyright, use freely\0",
            'wtpt' => $xyz(0.95045, 1.00000, 1.08905),
            'rXYZ' => $xyz(0.43607, 0.22249, 0.01392),
            'gXYZ' => $xyz(0.38515, 0.71687, 0.09708),
            'bXYZ' => $xyz(0.14307, 0.06061, 0.71410),
            'rTRC' => 'curv'.str_repeat("\0", 4).pack('N', 1024).$curve,
        ];
        $tags['gTRC'] = $tags['rTRC'];
        $tags['bTRC'] = $tags['rTRC'];

        $offset = 128 + 4 + 12 * count($tags);
        $table = pack('N', count($tags));
        $data = '';
        $placed = [];

        foreach ($tags as $signature => $body) {
            /* The three tone curves are one curve: stored once, pointed at three times. */
            if (! isset($placed[$body])) {
                $placed[$body] = $offset + strlen($data);
                $data .= $body.str_repeat("\0", (4 - strlen($body) % 4) % 4);
            }

            $table .= $signature.pack('NN', $placed[$body], strlen($body));
        }

        $size = $offset + strlen($data);

        $header = pack('N', $size)
            ."\0\0\0\0"                      // preferred CMM: none
            .pack('N', 0x02100000)           // ICC version 2.1
            .'mntr'.'RGB '.'XYZ '
            .pack('n6', 2000, 1, 1, 0, 0, 0) // creation date, fixed so the profile is byte-stable
            .'acsp'
            .str_repeat("\0", 4 * 4 + 8)     // platform, flags, manufacturer, model, attributes
            .pack('N', 0)                    // perceptual intent
            .pack('N3', 63190, 65536, 54061) // PCS illuminant D50
            .str_repeat("\0", 48);           // creator, then reserved

        return $profile = $header.$table.$data;
    }

    /** The sRGB profile as a whole PNG iCCP chunk, ready to sit after IHDR. */
    public static function pngChunk(): string
    {
        $payload = self::DESCRIPTION."\0\0".gzcompress(self::srgb(), 9);

        return pack('N', strlen($payload)).'iCCP'.$payload.pack('N', crc32('iCCP'.$payload));
    }

    /** The sRGB profile as a whole JPEG APP2 segment, marker included. */
    public static function jpegSegment(): string
    {
        $segment = "ICC_PROFILE\0\x01\x01".self::srgb();

        return "\xFF\xE2".pack('n', strlen($segment) + 2).$segment;
    }

    /**
     * Adobe RGB numbers to sRGB numbers, pixel by pixel, re-encoded with the
     * sRGB profile. Null when GD cannot read the file.
     */
    private static function convert(string $bytes): ?string
    {
        $isPng = str_starts_with($bytes, self::PNG_SIGNATURE);
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);

        /* Lookup tables: Adobe RGB code value to linear light, linear light to sRGB code value. */
        $linear = [];
        for ($i = 0; $i < 256; $i++) {
            $linear[$i] = ($i / 255) ** self::ADOBE_RGB_GAMMA;
        }

        $steps = 4096;
        $encode = [];
        for ($i = 0; $i <= $steps; $i++) {
            $v = $i / $steps;
            $encode[$i] = (int) round(($v <= 0.0031308 ? 12.92 * $v : 1.055 * $v ** (1 / 2.4) - 0.055) * 255);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;

                /* Fully transparent: nothing to see, nothing to convert. */
                if ($alpha === 127) {
                    continue;
                }

                $r = $linear[($pixel >> 16) & 0xFF];
                $g = $linear[($pixel >> 8) & 0xFF];
                $b = $linear[$pixel & 0xFF];

                /* Linear Adobe RGB to linear sRGB; both share the D65 white, so green carries over. */
                $red = 1.39835 * $r - 0.39835 * $g;
                $blue = -0.04292 * $g + 1.04292 * $b;

                imagesetpixel($image, $x, $y, ($alpha << 24)
                    | (($red <= 0 ? 0 : ($red >= 1 ? 255 : $encode[(int) ($red * $steps + 0.5)])) << 16)
                    | ($encode[(int) ($g * $steps + 0.5)] << 8)
                    | ($blue <= 0 ? 0 : ($blue >= 1 ? 255 : $encode[(int) ($blue * $steps + 0.5)])));
            }
        }

        ob_start();

        if ($isPng) {
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 92);
        }

        $out = ob_get_clean();

        return $isPng ? self::embedInPng($out) : self::embedInJpeg($out);
    }

    /** GD's PNG with the sRGB profile straight after the header. */
    private static function embedInPng(string $png): string
    {
        $afterHeader = 8 + 12 + unpack('N', substr($png, 8, 4))[1];

        return substr($png, 0, $afterHeader).self::pngChunk().substr($png, $afterHeader);
    }

    /** GD's JPEG with the sRGB profile after its JFIF header, which has to stay first. */
    private static function embedInJpeg(string $jpeg): string
    {
        $insertAt = 2;

        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $insertAt = 4 + unpack('n', substr($jpeg, 4, 2))[1];
        }

        return substr($jpeg, 0, $insertAt).self::jpegSegment().substr($jpeg, $insertAt);
    }

    /** Which space an embedded profile describes, by its name. */
    private static function profileSpace(string $icc): string
    {
        /* Version 2 profiles name themselves in ASCII, version 4 in UTF-16. */
        $has = fn (string $name) => str_contains($icc, $name)
            || str_contains($icc, mb_convert_encoding($name, 'UTF-16BE', 'UTF-8'));

        return match (true) {
            $has('Adobe RGB') => self::ADOBE_RGB,
            $has('sRGB') => self::SRGB,
            default => self::OTHER,
        };
    }

    /** EXIF ColorSpace, from XMP or binary EXIF of either byte order: 1 is sRGB, 65535 "uncalibrated". */
    private static function exifColourSpace(string $metadata): ?int
    {
        if (preg_match('~exif:ColorSpace(?:="|>)(\d+)~', $metadata, $m)) {
            return (int) $m[1];
        }

        /* Binary tag 0xA001, type SHORT, count 1, then the value. */
        if (preg_match('~\xA0\x01\x00\x03\x00\x00\x00\x01(..)~s', $metadata, $m)) {
            return unpack('n', $m[1])[1];
        }

        if (preg_match('~\x01\xA0\x03\x00\x01\x00\x00\x00(..)~s', $metadata, $m)) {
            return unpack('v', $m[1])[1];
        }

        return null;
    }

    /** @return array{0: ?string, 1: bool, 2: string} the profile, whether an sRGB chunk is present, metadata text */
    private static function readPng(string $bytes): array
    {
        $icc = null;
        $srgb = false;
        $metadata = '';

        for ($at = 8; $at + 12 <= strlen($bytes);) {
            $length = unpack('N', substr($bytes, $at, 4))[1];
            $type = substr($bytes, $at + 4, 4);
            $data = substr($bytes, $at + 8, $length);

            if ($type === 'iCCP') {
                $name = strstr($data, "\0", true) ?: '';
                /* The name, its terminator, the compression method, then the zlib stream. */
                $icc = $name.' '.(@gzuncompress(substr($data, strlen($name) + 2)) ?: '');
            } elseif ($type === 'sRGB') {
                $srgb = true;
            } elseif (in_array($type, ['iTXt', 'tEXt', 'zTXt', 'eXIf'], true)) {
                $metadata .= $data;
            } elseif ($type === 'IDAT' || $type === 'IEND') {
                break;
            }

            $at += 12 + $length;
        }

        return [$icc, $srgb, $metadata];
    }

    /** @return array{0: ?string, 1: string} the profile, metadata text */
    private static function readJpeg(string $bytes): array
    {
        $icc = null;
        $metadata = '';

        for ($at = 2; $at + 4 <= strlen($bytes) && $bytes[$at] === "\xFF";) {
            $marker = ord($bytes[$at + 1]);

            /* Start of scan: the headers are over. */
            if ($marker === 0xDA) {
                break;
            }

            $length = unpack('n', substr($bytes, $at + 2, 2))[1];
            $segment = substr($bytes, $at + 4, $length - 2);

            if ($marker === 0xE2 && str_starts_with($segment, "ICC_PROFILE\0")) {
                /* A large profile spans several segments, each after a 2-byte sequence number. */
                $icc = ($icc ?? '').substr($segment, 14);
            } elseif ($marker === 0xE1) {
                $metadata .= $segment;
            }

            $at += 2 + $length;
        }

        return [$icc, $metadata];
    }
}
