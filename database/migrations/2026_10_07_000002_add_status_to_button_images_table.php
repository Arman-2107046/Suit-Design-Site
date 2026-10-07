<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Button styles can be switched off like fabrics and lining cloths. A hidden
 * style takes every body-button layer that uses it out of the configurator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('button_images', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('diagram');
        });
    }

    public function down(): void
    {
        Schema::table('button_images', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
