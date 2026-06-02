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
        Schema::create('erp_bill_statuses', function (Blueprint $table) {
            $table->string('billno')->primary();
            $table->timestamp('date')->nullable();
            $table->string('cucode')->index();
            $table->string('cuname')->nullable();
            $table->decimal('netamount', 12, 2)->default(0);
            $table->decimal('amtreceived', 12, 2)->default(0);
            $table->char('settled', 1)->default('N');
            $table->integer('ddays')->default(0);
            $table->integer('lockdays')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_bill_statuses');
    }
};
