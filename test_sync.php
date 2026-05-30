<?php
$cucodes = ["010311", "010312", "010313", "010314", "010315"];
$baseUrl = rtrim(config("services.external_billing.url"), "/");
$total = 0;

foreach ($cucodes as $cucode) {
    $customer = \App\Models\Customer::where("external_cucode", $cucode)->first();
    if (!$customer) continue;

    $res = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
        "ngrok-skip-browser-warning" => "true"
    ])->asMultipart()->post("{$baseUrl}/API/announcements/bill_master.php", [
        ["name" => "from_date", "contents" => "2023-01-01"],
        ["name" => "to_date", "contents" => "2024-12-31"],
        ["name" => "cucode", "contents" => $cucode]
    ]);

    if ($res->successful()) {
        $data = $res->json();
        if (isset($data["data"])) {
            foreach ($data["data"] as $bill) {
                if (!isset($bill["BILLNO"]) && !isset($bill["BN"])) continue;
                $billDate = isset($bill["DATE"]) ? \Carbon\Carbon::parse($bill["DATE"]) : now();
                \App\Models\Bill::updateOrCreate(
                    ["invoice_no" => $bill["BILLNO"] ?? $bill["BN"]],
                    [
                        "customer_id" => $customer->id,
                        "bill_date" => $billDate->format("Y-m-d"),
                        "due_date" => $billDate->copy()->addDays(30)->format("Y-m-d"),
                        "grand_total" => $bill["NETAMOUNT"] ?? 0,
                        "subtotal" => $bill["NETAMOUNT"] ?? 0,
                        "gst_total" => 0,
                        "status" => $billDate->copy()->addDays(30)->isPast() ? "overdue" : "unpaid",
                        "payment_status" => "unpaid",
                    ]
                );
                $total++;
            }
        }
    }
}
echo "Synced $total bills.";

