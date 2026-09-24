<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A custom lining is a lining, not a lining-for-one-fabric.
 *
 * Every combination had to be uploaded once per outer fabric, so the same
 * five linings existed 22 times over, and a lining only appeared on the
 * fabrics someone had got round to rendering. They are now keyed by lining
 * type and lining fabric alone, and offered on every fabric.
 *
 * Collapsing keeps the oldest row of each combination. The images belonging
 * to the rows it drops were renders against one particular outer fabric and
 * mean nothing once the tie is gone — re-upload under the new naming
 * (CL_LiningType_LiningFabric) to replace them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('custom_linings')
            ->selectRaw('MIN(id) as id')
            ->groupBy('lining_type_id', 'custom_lining_fabric_id')
            ->pluck('id');

        DB::table('custom_linings')->whereNotIn('id', $keep)->delete();


        /*
         * SQLite refuses to drop a column named in a foreign key definition, so
         * there the table is rebuilt around the columns that remain.
         */
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildWithoutFabric();

            return;
        }


        /* MySQL holds the composite unique open for the foreign key that leans
           on its leftmost column, so that constraint goes first. */
        Schema::table('custom_linings', function (Blueprint $table) {
            $table->dropForeign(['fabric_id']);
        });

        Schema::table('custom_linings', function (Blueprint $table) {
            $table->dropUnique('custom_lining_unique');
        });

        Schema::table('custom_linings', function (Blueprint $table) {
            $table->dropColumn('fabric_id');
        });

        Schema::table('custom_linings', function (Blueprint $table) {
            $table->unique(
                ['lining_type_id', 'custom_lining_fabric_id'],
                'custom_lining_unique'
            );
        });
    }

    /**
     * Reversible in shape only: which fabric each lining belonged to is gone,
     * so the column comes back nullable and empty.
     */
    public function down(): void
    {
        Schema::table('custom_linings', function (Blueprint $table) {
            $table->dropUnique('custom_lining_unique');
        });

        Schema::table('custom_linings', function (Blueprint $table) {
            $table->foreignId('fabric_id')->nullable()->after('sort_order')->constrained('fabrics')->cascadeOnDelete();
        });

        Schema::table('custom_linings', function (Blueprint $table) {
            $table->unique(
                ['fabric_id', 'custom_lining_fabric_id', 'lining_type_id'],
                'custom_lining_unique'
            );
        });
    }

    private function rebuildWithoutFabric(): void
    {
        /* Index names are database-wide in SQLite, so the old one has to go
           before the rebuilt table can claim it. */
        Schema::table('custom_linings', function (Blueprint $table) {
            $table->dropUnique('custom_lining_unique');
        });


        Schema::create('custom_linings_rebuilt', function (Blueprint $table) {
            $table->id();
            $table->integer('sort_order')->default(0);
            $table->foreignId('custom_lining_fabric_id')->constrained('custom_lining_fabrics')->cascadeOnDelete();
            $table->foreignId('lining_type_id')->constrained('lining_types')->cascadeOnDelete();
            $table->string('image');
            $table->integer('layer_index')->default(100);
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(
                ['lining_type_id', 'custom_lining_fabric_id'],
                'custom_lining_unique'
            );
        });


        $columns = 'id, sort_order, custom_lining_fabric_id, lining_type_id, image, layer_index, is_default, status, created_at, updated_at';

        DB::statement("insert into custom_linings_rebuilt ({$columns}) select {$columns} from custom_linings");


        Schema::drop('custom_linings');

        Schema::rename('custom_linings_rebuilt', 'custom_linings');
    }
};
