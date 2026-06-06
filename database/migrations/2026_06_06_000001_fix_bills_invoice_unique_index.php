<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the bills table: replace the global unique index on invoice_no
     * with a composite unique index on (customer_id, invoice_no).
     *
     * invoice_no is NOT globally unique — the same number can appear for
     * different customers in the ERP. The old constraint silently overwrote
     * bills from different customers that shared an invoice number during sync.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            // Step 1: Drop the old, incorrect global unique index on invoice_no
            $table->dropUnique(['invoice_no']);

            // Step 2: Add the correct composite unique index
            $table->unique(['customer_id', 'invoice_no'], 'bills_customer_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_customer_invoice_unique');
            $table->unique('invoice_no');
        });
    }
};
