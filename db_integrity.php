<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$issues = [];

// 1. Customers without Users
$orphanedCustomers = \App\Models\Customer::whereDoesntHave("user")->count();
if ($orphanedCustomers > 0) $issues[] = "$orphanedCustomers Customers without associated Users.";

// 2. Bills without Customers
$orphanedBills = \App\Models\Bill::whereDoesntHave("customer")->count();
if ($orphanedBills > 0) $issues[] = "$orphanedBills Bills without associated Customers.";

// 3. Duplicate external cucodes in customers
$dupCucodes = \App\Models\Customer::select("external_cucode")->groupBy("external_cucode")->havingRaw("COUNT(*) > 1")->count();
if ($dupCucodes > 0) $issues[] = "$dupCucodes duplicate external_cucode entries found in customers table.";

// 4. Inconsistent bill statuses
$invalidStatusBills = \App\Models\Bill::whereNotIn("status", ["active", "cancelled", "paid", "unpaid"])->count();
if ($invalidStatusBills > 0) $issues[] = "$invalidStatusBills Bills with invalid status.";

// 5. Duplicate invoice_no in bills
$dupInvoices = \App\Models\Bill::select("invoice_no")->groupBy("invoice_no")->havingRaw("COUNT(*) > 1")->count();
if ($dupInvoices > 0) $issues[] = "$dupInvoices duplicate invoice_no entries found in bills table.";

if (empty($issues)) {
    echo "Database Integrity: PASS\n";
} else {
    echo "Database Integrity Issues Found:\n" . implode("\n", $issues) . "\n";
}

