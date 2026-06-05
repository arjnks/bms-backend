<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$customers = App\Models\Customer::with("bills")->get();
$zeroCount = 0;
foreach($customers as $c) {
    if($c->bills->count() > 0) {
        $outstanding = $c->bills->where("is_settled", false)->sum(function($b) { return max(0, $b->grand_total - $b->amount_received); });
        if($outstanding == 0) {
            $zeroCount++;
            echo "Customer ID {$c->id} (Code: {$c->external_cucode}) has {$c->bills->count()} bills but 0 outstanding.\n";
            foreach($c->bills as $b) {
                echo "  Bill {$b->invoice_no}: net={$b->grand_total}, recv={$b->amount_received}, settled={$b->is_settled}\n";
            }
            if($zeroCount > 3) break;
        }
    }
}

