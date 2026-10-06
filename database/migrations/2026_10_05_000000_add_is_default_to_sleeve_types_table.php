<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Which shoulder every fabric opens on.
 *
 * Each fabric carries its own set of sleeves, and a default flag on each of
 * those meant 35 edits to change it — so in practice only one fabric ever had
 * one, and the rest opened on whichever shoulder happened to sort first. One
 * flag on the shoulder style decides it for every fabric at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sleeve_types', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('diagram');
        });
    }

    public function down(): void
    {
        Schema::table('sleeve_types', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
