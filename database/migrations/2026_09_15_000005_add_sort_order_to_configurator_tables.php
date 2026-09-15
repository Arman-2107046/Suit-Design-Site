<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'bodies',
        'body_buttons',
        'body_types',
        'button_images',
        'chest_pocket_types',
        'chest_pockets',
        'custom_lining_fabrics',
        'custom_linings',
        'default_linings',
        'lapel_categories',
        'lapel_sub_categories',
        'lapels',
        'lining_types',
        'side_pocket_types',
        'side_pockets',
        'sleeve_types',
        'sleeves',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('sort_order')->default(0)->after('id')->index();
            });

            // Keep the current (creation) order as the starting point.
            DB::table($table)->orderBy('id')->pluck('id')->each(
                fn (int $id, int $index) => DB::table($table)->where('id', $id)->update(['sort_order' => $index + 1])
            );
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('sort_order'));
        }
    }
};
