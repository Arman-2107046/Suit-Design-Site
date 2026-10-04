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

    public function test_an_untagged_adobe_rgb_png_gains_the_profile_right_after_its_header(): void
    {
        $original = self::png(self::xmp(65535), self::chunk('gAMA', pack('N', 45455)));
        $tagged = ColorProfile::tagAdobeRgb($original);

        /* Profile straight after the header; the contradicting gAMA gone; everything else kept in order. */
        $chunks = $this->chunks($tagged);
        $expected = array_values(array_diff(array_column($this->chunks($original), 0), ['gAMA']));
        array_splice($expected, 1, 0, ['iCCP']);
        $this->assertSame($expected, array_column($chunks, 0));

        /* The chunk is valid and carries the profile, zlib-compressed after its name. */
        $iccp = $chunks[1][1];
        $data = substr($iccp, 8, -4);
        $this->assertSame(crc32('iCCP'.$data), unpack('N', substr($iccp, -4))[1]);
        [$name, $rest] = explode("\0", $data, 2);
        $this->assertSame('ICC Profile', $name);
        $this->assertSame(ColorProfile::adobeRgb(), gzuncompress(substr($rest, 1)));

        /* Not a pixel changed. */
        $idat = fn (string $png) => array_values(array_filter($this->chunks($png), fn ($c) => $c[0] === 'IDAT'));
        $this->assertSame($idat($original), $idat($tagged));
        $this->assertNotFalse(imagecreatefromstring($tagged));
    }

    public function test_tagging_twice_changes_nothing_the_second_time(): void
    {
        $once = ColorProfile::tagAdobeRgb(self::png(self::xmp(65535)));

        $this->assertSame($once, ColorProfile::tagAdobeRgb($once));
        $this->assertFalse(ColorProfile::needsAdobeRgb($once));
    }

    public function test_images_that_are_srgb_or_say_nothing_are_left_exactly_as_they_are(): void
    {
        foreach ([
            'marked sRGB' => self::png(self::xmp(1)),
            'sRGB chunk' => self::png(self::chunk('sRGB', "\0"), self::xmp(65535)),
            'no metadata' => self::png(),
            'not an image' => 'GIF89a…',
        ] as $case => $bytes) {
            $this->assertSame($bytes, ColorProfile::tagAdobeRgb($bytes), $case);
        }
    }

    public function test_an_untagged_adobe_rgb_jpeg_gains_the_profile_after_its_exif(): void
    {
        $exif = "Exif\0\0".'MM'."\0\x2A\0\0\0\x08\0\x01".
            "\xA0\x01\x00\x03\x00\x00\x00\x01\xFF\xFF\0\0".str_repeat("\0", 4);
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
        $jpeg = "\xFF\xD8".$app1."\xFF\xDA\0\x02SCAN";

        $tagged = ColorProfile::tagAdobeRgb($jpeg);

        $this->assertSame("\xFF\xD8".$app1.ColorProfile::jpegSegment()."\xFF\xDA\0\x02SCAN", $tagged);
        $this->assertSame($tagged, ColorProfile::tagAdobeRgb($tagged));
    }

    public function test_the_profile_is_a_well_formed_icc_display_profile(): void
    {
        $icc = ColorProfile::adobeRgb();

        $this->assertSame(strlen($icc), unpack('N', substr($icc, 0, 4))[1]);
        $this->assertSame('mntrRGB XYZ ', substr($icc, 12, 12));
        $this->assertSame('acsp', substr($icc, 36, 4));

        $count = unpack('N', substr($icc, 128, 4))[1];
        for ($i = 0; $i < $count; $i++) {
            ['sig' => $sig, 'offset' => $offset, 'size' => $size] = unpack('a4sig/Noffset/Nsize', substr($icc, 132 + 12 * $i, 12));
            $this->assertSame(0, $offset % 4, "{$sig} is aligned");
            $this->assertLessThanOrEqual(strlen($icc), $offset + $size, "{$sig} fits");
        }

        /* Adobe RGB's 563/256 gamma */
        $this->assertStringContainsString('curv'."\0\0\0\0".pack('Nn', 1, 0x0233), $icc);
    }
}
