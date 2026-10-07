<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Journal posts are written by administrators, so their author is an admin
 * now, not a customer account. `php artisan admin:create --from-user=…`
 * carries a person's existing posts across when it moves them; user_id is
 * left in place until then so no authorship is lost on the way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });
    }
};
