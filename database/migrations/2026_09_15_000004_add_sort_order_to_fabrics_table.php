<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('id')->index();
        });

        // Keep the current (creation) order as the starting point.
        DB::table('fabrics')->orderBy('id')->pluck('id')->each(
            fn (int $id, int $index) => DB::table('fabrics')->where('id', $id)->update(['sort_order' => $index + 1])
        );
    }

    public function down(): void
    {
        Schema::table('fabrics', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
