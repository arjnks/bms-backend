<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$page = 1;
$totalSynced = 0;
while (true) {
    echo "Fetching page $page...\n";
    $response = \Illuminate\Support\Facades\Http::get("https://billing.leopharma.tech/API/announcements/bill_master_acc1.php", ["page" => $page]);
    if (!$response->successful()) { echo "Failed HTTP\n"; break; }
    
    $json = $response->json();
    $data = $json["data"] ?? $json ?? [];
    if (empty($data)) break;
    
    $updates = [];
    foreach ($data as $b) {
        if (empty($b["billno"])) continue;
        
        $netamount = (float) ($b["netamount"] ?? 0);
        $amtreceived = (float) ($b["amountrecieved"] ?? $b["amtreceived"] ?? $b["amount_received"] ?? 0);
        $isSettled = (($b["settled"] ?? "N") === "Y");
        
        // Find bill and update
        $bill = \App\Models\Bill::where("invoice_no", (string)$b["billno"])->first();
        if ($bill) {
            $bill->amount_received = $amtreceived;
            $bill->is_settled = $isSettled;
            $bill->payment_status = $isSettled ? "paid" : "unpaid";
            if ($isSettled) {
                $bill->status = "paid";
            } else {
                $bill->status = ($bill->aging_days > $bill->lock_days) ? "overdue" : "unpaid";
            }
            $bill->save();
            $totalSynced++;
        }
    }
    echo "Updated $totalSynced bills so far...\n";
    $page++;
}
echo "Done fixing. Total updated: $totalSynced\n";

