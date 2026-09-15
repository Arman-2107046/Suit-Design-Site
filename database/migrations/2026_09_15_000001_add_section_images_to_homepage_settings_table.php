<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IMAGES = ['suits', 'designer', 'planet', 'tailor'];

    private const EXISTING_URLS = ['hero_image_url', 'instagram_url', 'facebook_url', 'x_url', 'pinterest_url', 'tiktok_url'];

    public function up(): void
    {
        // varchar(2048) x many columns blows MySQL's 64 KB row limit; URLs belong in TEXT.
        Schema::table('homepage_settings', function (Blueprint $table) {
            foreach (self::EXISTING_URLS as $column) {
                $table->text($column)->nullable()->change();
            }
        });

        foreach (self::IMAGES as $image) {
            foreach (["{$image}_image" => 'string', "{$image}_image_url" => 'text'] as $column => $type) {
                if (Schema::hasColumn('homepage_settings', $column)) {
                    Schema::table('homepage_settings', fn (Blueprint $table) => $table->{$type}($column)->nullable()->change());

                    continue;
                }

                Schema::table('homepage_settings', fn (Blueprint $table) => $table->{$type}($column)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table) {
            foreach (self::IMAGES as $image) {
                $table->dropColumn(["{$image}_image", "{$image}_image_url"]);
            }
        });
    }
};
