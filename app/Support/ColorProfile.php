<?php

namespace App\Support;

/*
 * Making sure an image says which colours its numbers mean.
 *
 * The renders are exported from Photoshop documents set up in Adobe RGB
 * (1998), and some exports leave the profile out. Photoshop still notes in the
 * file's EXIF/XMP that the colour space is "uncalibrated" (65535) — its way of
 * saying "not sRGB" — but a browser that finds no profile has to assume sRGB.
 * Adobe RGB numbers read as sRGB come out flat and washed out, which is why a
 * body exported that way looked faded beside sleeves exported in sRGB.
 *
 * The fix is to attach the profile the pixels were made in, losslessly: no
 * pixel is touched, the file only gains the description it was missing.
 * Cloudflare then converts to sRGB as it resizes, and a browser shown the
 * original converts it too, so every layer lands in the same colour space.
 *
 * Files that already carry a profile (or an sRGB chunk), and files Photoshop
 * marked as sRGB, are left exactly as they are.
 */
class ColorProfile
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /* Adobe RGB's tone curve is a pure power of 563/256 (≈ 2.2), stored as u8Fixed8. */
    private const ADOBE_RGB_GAMMA = 0x0233;

    /**
     * The file with an Adobe RGB profile attached when it needs one, or
     * exactly as given when it does not (or is not a PNG or JPEG).
     */
    public static function tagAdobeRgb(string $bytes): string
    {
        if (str_starts_with($bytes, self::PNG_SIGNATURE)) {
            return self::tagPng($bytes) ?? $bytes;
        }

        if (str_starts_with($bytes, "\xFF\xD8")) {
            return self::tagJpeg($bytes) ?? $bytes;
        }

        return $bytes;
    }

    public static function needsAdobeRgb(string $bytes): bool
    {
        return self::tagAdobeRgb($bytes) !== $bytes;
    }

    /**
     * An ICC v2 display profile matching Adobe RGB (1998): its D50-adapted
     * primaries, D65 white and 563/256 gamma — the same numbers as the profile
     * Photoshop embeds, so colour engines treat the two identically.
     */
    public static function adobeRgb(): string
    {
        static $profile;

        if ($profile !== null) {
            return $profile;
        }

        $xyz = fn (float ...$v) => 'XYZ '.str_repeat("\0", 4).implode('', array_map(
            fn ($n) => pack('N', (int) round($n * 65536)), $v
        ));

        $description = 'Adobe RGB (1998) compatible';

        $tags = [
            'desc' => 'desc'.str_repeat("\0", 4)
                .pack('N', strlen($description) + 1).$description."\0"
                .pack('NN', 0, 0)            // no Unicode description
                .pack('nC', 0, 0).str_repeat("\0", 67), // no ScriptCode description
            'cprt' => 'text'.str_repeat("\0", 4)."No copyright, use freely\0",
            'wtpt' => $xyz(0.95045, 1.00000, 1.08905),
            'rXYZ' => $xyz(0.60974, 0.31111, 0.01947),
            'gXYZ' => $xyz(0.20528, 0.62567, 0.06087),
            'bXYZ' => $xyz(0.14919, 0.06322, 0.74457),
            'rTRC' => 'curv'.str_repeat("\0", 4).pack('Nn', 1, self::ADOBE_RGB_GAMMA)."\0\0",
        ];
        $tags['gTRC'] = $tags['rTRC'];
        $tags['bTRC'] = $tags['rTRC'];

        $offset = 128 + 4 + 12 * count($tags);
        $table = pack('N', count($tags));
        $data = '';

        foreach ($tags as $signature => $body) {
            $table .= $signature.pack('NN', $offset + strlen($data), strlen($body));
            $data .= $body.str_repeat("\0", (4 - strlen($body) % 4) % 4);
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

    /** Whether Photoshop recorded the colour space as "uncalibrated" — anything but sRGB. */
    private static function markedUncalibrated(string $metadata): bool
    {
        return (bool) preg_match('~exif:ColorSpace(?:="|>)65535~', $metadata)
            /* Binary EXIF tag 0xA001, SHORT, count 1, value 0xFFFF — in either byte order. */
            || str_contains($metadata, "\xA0\x01\x00\x03\x00\x00\x00\x01\xFF\xFF")
            || str_contains($metadata, "\x01\xA0\x03\x00\x01\x00\x00\x00\xFF\xFF");
    }

    private static function tagPng(string $bytes): ?string
    {
        $chunks = [];
        $metadata = '';

        for ($at = 8; $at + 12 <= strlen($bytes);) {
            $length = unpack('N', substr($bytes, $at, 4))[1];
            $type = substr($bytes, $at + 4, 4);
            $chunks[] = [$type, $at, 12 + $length];

            if ($type === 'iCCP' || $type === 'sRGB') {
                return null;
            }

            if (in_array($type, ['iTXt', 'tEXt', 'zTXt', 'eXIf'], true)) {
                $metadata .= substr($bytes, $at + 8, $length);
            }

            if ($type === 'IEND') {
                break;
            }

            $at += 12 + $length;
        }

        if (($chunks[0][0] ?? null) !== 'IHDR' || ! self::markedUncalibrated($metadata)) {
            return null;
        }

        $iccp = self::pngChunk();
        $out = self::PNG_SIGNATURE;

        foreach ($chunks as [$type, $at, $size]) {
            /* With a profile present these would only contradict it. */
            if ($type === 'gAMA' || $type === 'cHRM') {
                continue;
            }

            $out .= substr($bytes, $at, $size);

            if ($type === 'IHDR') {
                $out .= $iccp;
            }
        }

        return $out;
    }

    private static function tagJpeg(string $bytes): ?string
    {
        $metadata = '';
        $insertAt = 2;

        for ($at = 2; $at + 4 <= strlen($bytes) && $bytes[$at] === "\xFF";) {
            $marker = ord($bytes[$at + 1]);

            /* Start of scan: the headers are over. */
            if ($marker === 0xDA) {
                break;
            }

            $length = unpack('n', substr($bytes, $at + 2, 2))[1];
            $segment = substr($bytes, $at + 4, $length - 2);

            if ($marker === 0xE2 && str_starts_with($segment, "ICC_PROFILE\0")) {
                return null;
            }

            if ($marker === 0xE1) {
                $metadata .= $segment;
            }

            /* JFIF and EXIF have to stay first; the profile goes straight after them. */
            if ($marker === 0xE0 || $marker === 0xE1) {
                $insertAt = $at + 2 + $length;
            }

            $at += 2 + $length;
        }

        if (! self::markedUncalibrated($metadata)) {
            return null;
        }

        return substr($bytes, 0, $insertAt).self::jpegSegment().substr($bytes, $insertAt);
    }

    /** The profile as a whole PNG iCCP chunk, ready to sit after IHDR. */
    public static function pngChunk(): string
    {
        $payload = "ICC Profile\0\0".gzcompress(self::adobeRgb(), 9);

        return pack('N', strlen($payload)).'iCCP'.$payload.pack('N', crc32('iCCP'.$payload));
    }

    /** The profile as a whole JPEG APP2 segment, marker included. */
    public static function jpegSegment(): string
    {
        $segment = "ICC_PROFILE\0\x01\x01".self::adobeRgb();

        return "\xFF\xE2".pack('n', strlen($segment) + 2).$segment;
    }
}
