<?php

namespace Tests\Unit;

use App\Support\ColorProfile;
use PHPUnit\Framework\TestCase;

class ColorProfileTest extends TestCase
{
    /** A real 2x2 PNG, with whatever metadata chunks the test wants after IHDR. */
    public static function png(string ...$chunks): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 76, 110, 148));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        /* signature (8) + IHDR (12 + 13) */
        return substr($png, 0, 33).implode('', $chunks).substr($png, 33);
    }

    public static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /** XMP the way Photoshop writes it, saying the colour space is (65535) or is not (1) sRGB. */
    public static function xmp(int $colorSpace): string
    {
        return self::chunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0".'<x:xmpmeta><rdf:Description exif:ColorSpace="'.$colorSpace.'"/></x:xmpmeta>');
    }

    /** @return array<int, array{0: string, 1: string}> type => whole chunk */
    private function chunks(string $png): array
    {
        $out = [];
        for ($at = 8; $at < strlen($png);) {
            $length = unpack('N', substr($png, $at, 4))[1];
            $out[] = [substr($png, $at + 4, 4), substr($png, $at, 12 + $length)];
            $at += 12 + $length;
        }

        return $out;
    }

    /** One pixel's colour and alpha (0 opaque … 127 clear), as GD reads it. */
    private function pixel(string $image, int $x = 0, int $y = 0): array
    {
        $gd = imagecreatefromstring($image);
        $c = imagecolorat($gd, $x, $y);

        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, ($c >> 24) & 0x7F];
    }

    /** Adobe RGB to sRGB the long way round, to check the lookup tables against. */
    private function expected(int $r, int $g, int $b): array
    {
        $lin = fn (int $v) => ($v / 255) ** (563 / 256);
        $enc = fn (float $v) => (int) round(255 * ($v <= 0.0031308 ? 12.92 * max($v, 0) : 1.055 * min($v, 1) ** (1 / 2.4) - 0.055));

        return [
            $enc(1.39835 * $lin($r) - 0.39835 * $lin($g)),
            $enc($lin($g)),
            $enc(-0.04292 * $lin($g) + 1.04292 * $lin($b)),
        ];
    }

    private function assertColourNear(array $expected, array $actual, int $delta = 1): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->assertEqualsWithDelta($expected[$i], $actual[$i], $delta, "channel {$i}");
        }
    }

    public function test_an_adobe_rgb_png_has_its_colours_converted_and_carries_the_srgb_profile(): void
    {
        $original = self::png(self::xmp(65535), self::chunk('gAMA', pack('N', 45455)));
        $converted = ColorProfile::toSrgb($original);

        /* The sRGB profile sits straight after the header, valid and named as tools expect. */
        $chunks = $this->chunks($converted);
        $this->assertSame(['IHDR', 'iCCP'], array_column(array_slice($chunks, 0, 2), 0));
        $this->assertNotContains('gAMA', array_column($chunks, 0));
        $data = substr($chunks[1][1], 8, -4);
        $this->assertSame(crc32('iCCP'.$data), unpack('N', substr($chunks[1][1], -4))[1]);
        [$name, $rest] = explode("\0", $data, 2);
        $this->assertSame('sRGB IEC61966-2.1', $name);
        $this->assertSame(ColorProfile::srgb(), gzuncompress(substr($rest, 1)));

        /* The colours are the sRGB equivalents: the same numbers the designer would show. */
        $this->assertColourNear($this->expected(76, 110, 148), $this->pixel($converted));
        $this->assertSame(ColorProfile::SRGB, ColorProfile::colourSpace($converted));
    }

    public function test_a_png_with_photoshops_adobe_rgb_profile_is_converted_too(): void
    {
        /* A well-formed profile that names itself Adobe RGB, as Photoshop's does */
        $adobeProfile = str_replace('sRGB IEC61966-2.1', 'Adobe RGB (1998) ', ColorProfile::srgb());
        $original = self::png(self::chunk('iCCP', "Adobe RGB (1998)\0\0".gzcompress($adobeProfile)));

        $this->assertSame(ColorProfile::ADOBE_RGB, ColorProfile::colourSpace($original));

        $converted = ColorProfile::toSrgb($original);
        $this->assertSame(ColorProfile::SRGB, ColorProfile::colourSpace($converted));
        $this->assertColourNear($this->expected(76, 110, 148), $this->pixel($converted));
    }

    public function test_converting_twice_changes_nothing_the_second_time(): void
    {
        $once = ColorProfile::toSrgb(self::png(self::xmp(65535)));

        $this->assertSame($once, ColorProfile::toSrgb($once));
        $this->assertSame($once, ColorProfile::toSrgb($once, unmarkedIsAdobeRgb: true));
    }

    public function test_transparency_is_kept_and_clear_pixels_are_left_alone(): void
    {
        $image = imagecreatetruecolor(2, 1);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 200, 40, 90, 64));
        imagesetpixel($image, 1, 0, imagecolorallocatealpha($image, 10, 20, 30, 127));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $png = substr($png, 0, 33).self::xmp(65535).substr($png, 33);

        $converted = ColorProfile::toSrgb($png);

        $half = $this->pixel($converted);
        $this->assertSame(64, $half[3]);
        $this->assertColourNear($this->expected(200, 40, 90), $half);
        $this->assertSame(127, $this->pixel($converted, 1)[3]);
    }

    public function test_images_in_srgb_another_space_or_not_png_or_jpeg_are_left_exactly_as_they_are(): void
    {
        foreach ([
            'marked sRGB' => self::png(self::xmp(1)),
            'sRGB chunk' => self::png(self::chunk('sRGB', "\0"), self::xmp(65535)),
            'Display P3 profile' => self::png(self::chunk('iCCP', "Display P3\0\0".gzcompress('desc Display P3'))),
            'not an image' => 'GIF89a…',
        ] as $case => $bytes) {
            $this->assertSame($bytes, ColorProfile::toSrgb($bytes), $case);
            $this->assertSame($bytes, ColorProfile::toSrgb($bytes, unmarkedIsAdobeRgb: true), $case);
        }
    }

    public function test_an_image_that_says_nothing_is_converted_only_when_asked(): void
    {
        $plain = self::png();

        $this->assertSame(ColorProfile::UNKNOWN, ColorProfile::colourSpace($plain));
        $this->assertSame($plain, ColorProfile::toSrgb($plain));

        $converted = ColorProfile::toSrgb($plain, unmarkedIsAdobeRgb: true);
        $this->assertSame(ColorProfile::SRGB, ColorProfile::colourSpace($converted));
        $this->assertColourNear($this->expected(76, 110, 148), $this->pixel($converted));
    }

    public function test_an_adobe_rgb_jpeg_is_converted_with_the_profile_after_its_jfif_header(): void
    {
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 76, 110, 148));
        ob_start();
        imagejpeg($image, null, 100);
        $jpeg = ob_get_clean();

        /* EXIF saying "uncalibrated", big-endian, straight after the JFIF header. */
        $exif = "Exif\0\0".'MM'."\0\x2A\0\0\0\x08\0\x01"."\xA0\x01\x00\x03\x00\x00\x00\x01\xFF\xFF\0\0".str_repeat("\0", 4);
        $afterJfif = 4 + unpack('n', substr($jpeg, 4, 2))[1];
        $jpeg = substr($jpeg, 0, $afterJfif)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, $afterJfif);
        $this->assertSame(ColorProfile::ADOBE_RGB, ColorProfile::colourSpace($jpeg));

        $converted = ColorProfile::toSrgb($jpeg);

        $jfif = 4 + unpack('n', substr($converted, 4, 2))[1];
        $this->assertSame("\xFF\xD8\xFF\xE0", substr($converted, 0, 4));
        $this->assertSame(ColorProfile::jpegSegment(), substr($converted, $jfif, strlen(ColorProfile::jpegSegment())));
        $this->assertSame(ColorProfile::SRGB, ColorProfile::colourSpace($converted));

        /* JPEG is lossy, so a little more room than a PNG gets. */
        $this->assertColourNear($this->expected(76, 110, 148), $this->pixel($converted, 4, 4), 3);
    }

    public function test_the_profile_is_a_well_formed_srgb_display_profile(): void
    {
        $icc = ColorProfile::srgb();

        $this->assertSame(strlen($icc), unpack('N', substr($icc, 0, 4))[1]);
        $this->assertSame('mntrRGB XYZ ', substr($icc, 12, 12));
        $this->assertSame('acsp', substr($icc, 36, 4));
        $this->assertStringContainsString('sRGB IEC61966-2.1', $icc);

        $tags = [];
        $count = unpack('N', substr($icc, 128, 4))[1];
        for ($i = 0; $i < $count; $i++) {
            ['sig' => $sig, 'offset' => $offset, 'size' => $size] = unpack('a4sig/Noffset/Nsize', substr($icc, 132 + 12 * $i, 12));
            $this->assertSame(0, $offset % 4, "{$sig} is aligned");
            $this->assertLessThanOrEqual(strlen($icc), $offset + $size, "{$sig} fits");
            $tags[$sig] = [$offset, $size];
        }

        $this->assertSame(['desc', 'cprt', 'wtpt', 'rXYZ', 'gXYZ', 'bXYZ', 'rTRC', 'gTRC', 'bTRC'], array_keys($tags));

        /* One 1024-point sRGB curve, shared by all three channels, running from black to white. */
        $this->assertSame($tags['rTRC'], $tags['gTRC']);
        $this->assertSame($tags['rTRC'], $tags['bTRC']);
        $curve = substr($icc, $tags['rTRC'][0], $tags['rTRC'][1]);
        $this->assertSame('curv', substr($curve, 0, 4));
        $this->assertSame(1024, unpack('N', substr($curve, 8, 4))[1]);
        $this->assertSame([0, 65535], [unpack('n', substr($curve, 12, 2))[1], unpack('n', substr($curve, -2))[1]]);
    }
}

