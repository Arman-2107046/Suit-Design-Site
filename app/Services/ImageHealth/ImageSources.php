<?php

namespace App\Services\ImageHealth;

use App\Models\BlogPost;
use App\Models\Body;
use App\Models\BodyButton;
use App\Models\BodyType;
use App\Models\ButtonImage;
use App\Models\ChestPocket;
use App\Models\ChestPocketType;
use App\Models\CustomLining;
use App\Models\CustomLiningFabric;
use App\Models\DefaultLining;
use App\Models\Fabric;
use App\Models\FabricImage;
use App\Models\Lapel;
use App\Models\LapelCategory;
use App\Models\LapelSubCategory;
use App\Models\LiningType;
use App\Models\Page;
use App\Models\SidePocket;
use App\Models\SidepocketType;
use App\Models\Sleeve;
use App\Models\SleeveType;
use Closure;
use Illuminate\Database\Eloquent\Model;

/*
 * Every place the site keeps a picture, so the health check can test them
 * all. "required" means the configurator needs the picture: a blank one is
 * itself a problem. Optional pictures (a post's cover) are only tested when set.
 */
final class ImageSources
{
    /**
     * @return list<array{group: string, model: class-string<Model>, field: string, required: bool, with: list<string>, label: Closure(Model): string}>
     */
    public static function records(): array
    {
        $name = fn (Model $m) => (string) ($m->getAttribute('name') ?: $m->getAttribute('title') ?: '#'.$m->getKey());
        $join = fn (...$parts) => implode(' · ', array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : null, $parts))) ?: null;

        return [
            self::source('Fabrics', Fabric::class, 'image', true, [], $name),
            self::source('Fabric pictures', FabricImage::class, 'url', true, ['fabric'], fn (FabricImage $m) => $join($m->fabric?->name, $m->kind) ?? "#{$m->id}"),
            self::source('Bodies', Body::class, 'image', true, ['fabric', 'bodyType'], fn (Body $m) => $join($m->fabric?->name, $m->bodyType?->name) ?? "#{$m->id}"),
            self::source('Body types', BodyType::class, 'diagram', true, [], $name),
            self::source('Body buttons', BodyButton::class, 'image', true, ['bodyType', 'buttonImage'], fn (BodyButton $m) => $join($m->bodyType?->name, $m->buttonImage?->name) ?? "#{$m->id}"),
            self::source('Button styles', ButtonImage::class, 'diagram', true, [], $name),
            self::source('Lapels', Lapel::class, 'image', true, ['fabric', 'body.bodyType', 'lapelCategory', 'lapelSubcategory'], fn (Lapel $m) => $join($m->fabric?->name, $m->body?->bodyType?->name, $m->lapelCategory?->name, $m->lapelSubcategory?->name) ?? "#{$m->id}"),
            self::source('Lapel styles', LapelCategory::class, 'diagram', true, [], $name),
            self::source('Lapel widths', LapelSubCategory::class, 'diagram', true, [], $name),
            self::source('Sleeves', Sleeve::class, 'image', true, ['fabric', 'sleeveType'], fn (Sleeve $m) => $join($m->fabric?->name, $m->sleeveType?->name) ?? "#{$m->id}"),
            self::source('Shoulder styles', SleeveType::class, 'diagram', true, [], $name),
            self::source('Chest pockets', ChestPocket::class, 'image', true, ['fabric', 'chestPocketType'], fn (ChestPocket $m) => $join($m->fabric?->name, $m->chestPocketType?->name) ?? "#{$m->id}"),
            self::source('Chest pocket styles', ChestPocketType::class, 'diagram', true, [], $name),
            self::source('Side pockets', SidePocket::class, 'image', true, ['fabric', 'sidePocketType'], fn (SidePocket $m) => $join($m->fabric?->name, $m->sidePocketType?->name) ?? "#{$m->id}"),
            self::source('Side pocket styles', SidepocketType::class, 'diagram', true, [], $name),
            self::source('Default linings', DefaultLining::class, 'image', true, ['fabric', 'bodyType', 'liningType'], fn (DefaultLining $m) => $join($m->fabric?->name, $m->bodyType?->name, $m->liningType?->name) ?? "#{$m->id}"),
            self::source('Custom linings', CustomLining::class, 'image', true, ['customLiningFabric', 'liningType'], fn (CustomLining $m) => $join($m->customLiningFabric?->name, $m->liningType?->name) ?? "#{$m->id}"),
            self::source('Lining cloths', CustomLiningFabric::class, 'image', true, [], $name),
            self::source('Lining types', LiningType::class, 'diagram', false, [], $name),
            self::source('Journal covers', BlogPost::class, 'cover_image_url', false, [], $name),
            self::source('Page headers', Page::class, 'hero_image_url', false, [], $name),
        ];
    }

    /**
     * Single-row settings pages, each field with the label an admin knows it by.
     *
     * @return array<class-string<Model>, array{group: string, page: class-string, fields: array<string, string>, lists?: array<string, string>}>
     */
    public static function settings(): array
    {
        return [
            \App\Models\HomepageSetting::class => [
                'group' => 'Homepage',
                'page' => \App\Filament\Pages\Homepage::class,
                'fields' => [
                    'hero_image_url' => 'Hero picture',
                    'suits_image_url' => 'Suits picture',
                    'designer_image_url' => 'Designer picture',
                    'designer_video_url' => 'Designer video',
                    'planet_image_url' => 'Planet picture',
                    'tailor_image_url' => 'Tailor picture',
                ],
                /* JSON lists of {url}: one entry per logo */
                'lists' => ['payment_logos' => 'Payment logo', 'shipping_logos' => 'Shipping logo'],
            ],
            \App\Models\SiteSetting::class => [
                'group' => 'Support pages',
                'page' => \App\Filament\Pages\SupportSettings::class,
                'fields' => [
                    'contact_image_url' => 'Contact page picture',
                    'samples_image_url' => 'Samples page picture',
                    'track_image_url' => 'Track order picture',
                ],
            ],
        ];
    }

    private static function source(string $group, string $model, string $field, bool $required, array $with, Closure $label): array
    {
        return compact('group', 'model', 'field', 'required', 'with', 'label');
    }
}
