<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Body buttons were rendering on top of the lapel.
 *
 * The configurator composites by layer_index, and buttons sat at 160 against
 * the lapel's 150, so a double-breasted lapel that should cover a button was
 * drawn under it. Buttons belong above the body (100) and below the lapel.
 *
 * Only rows still on the old default are moved; a value an admin chose
 * deliberately is left alone.
 */
return new class extends Migration
{
    private const WAS = 160;

    private const NOW = 120;

    public function up(): void
    {
        Schema::table('body_buttons', function (Blueprint $table) {
            $table->integer('layer_index')->default(self::NOW)->change();
        });

        DB::table('body_buttons')
            ->where('layer_index', self::WAS)
            ->update(['layer_index' => self::NOW]);
    }

    public function down(): void
    {
        Schema::table('body_buttons', function (Blueprint $table) {
            $table->integer('layer_index')->default(self::WAS)->change();
        });

        DB::table('body_buttons')
            ->where('layer_index', self::NOW)
            ->update(['layer_index' => self::WAS]);
    }
};
