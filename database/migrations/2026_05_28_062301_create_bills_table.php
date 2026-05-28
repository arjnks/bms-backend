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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('invoice_no')->unique();
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('gst_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);
            $table->enum('status', ['unpaid', 'overdue', 'paid'])->default('unpaid');
            $table->enum('payment_status', ['unpaid', 'payment_submitted', 'proof_rejected', 'paid'])->default('unpaid');
            $table->enum('payment_method', ['gpay', 'neft'])->nullable();
            $table->string('utr_number')->nullable();
            $table->string('proof_screenshot')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('bill_file_url')->nullable();
            $table->enum('bill_file_type', ['csv', 'excel', 'pdf'])->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
