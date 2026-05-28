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
        Schema::table('reminder_logs', function (Blueprint $table) {
            // bill_id was NOT NULL which caused bulk reminder to crash
            // Reminders are per-customer, not per-bill, so this must be nullable
            $table->unsignedBigInteger('bill_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('bill_id')->nullable(false)->change();
        });
    }
};
