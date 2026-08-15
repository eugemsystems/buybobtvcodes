<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('accent_color');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('commission_rate_applied', 5, 2)->nullable()->after('amount');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('commission_rate_applied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('commission_rate');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['commission_rate_applied', 'commission_amount']);
        });
    }
};
