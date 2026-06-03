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
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('amount_received', 10, 2)->default(0)->after('grand_total');
            $table->boolean('is_settled')->default(false)->after('amount_received');
            $table->integer('aging_days')->nullable()->after('is_settled');
            $table->integer('lock_days')->nullable()->after('aging_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['amount_received', 'is_settled', 'aging_days', 'lock_days']);
        });
    }
};
