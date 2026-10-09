<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * The pictures of a fabric's jacket with no lining, in two forms (kind): plain
     * "unlined" and "plate". One of each per fabric, the same for every jacket
     * style, offered as lining choices in the designer.
     */
    public function up(): void
    {
        Schema::create('unlined_linings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fabric_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('kind', 16)->default('unlined');

            /* The "Unlined" lining type, for the name and thumbnail of its tile. */
            $table->foreignId('lining_type_id')
                ->nullable()
                ->constrained('lining_types')
                ->nullOnDelete();

            $table->string('image');
            $table->unsignedInteger('layer_index')->default(100);
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique(['fabric_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlined_linings');
    }
};
