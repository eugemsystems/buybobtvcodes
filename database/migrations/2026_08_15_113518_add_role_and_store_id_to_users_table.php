<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('reseller')->after('password');
            $table->foreignId('store_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        DB::table('stores')->insert([
            'name' => 'Default Store',
            'slug' => 'default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Every user that existed before multi-tenancy was implicitly a full admin — preserve that.
        DB::table('users')->update(['role' => 'super_admin', 'store_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stores')->where('slug', 'default')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn('role');
        });
    }
};
