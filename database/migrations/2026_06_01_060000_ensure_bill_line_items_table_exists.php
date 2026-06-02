<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bill_line_items')) {
            return;
        }

        Schema::create('bill_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->onDelete('cascade');
            $table->string('product_name');
            $table->string('hsn_code')->nullable();
            $table->decimal('qty', 10, 2);
            $table->string('unit')->nullable();
            $table->decimal('rate', 10, 2);
            $table->decimal('gst_pct', 5, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        //
    }
};
