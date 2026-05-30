<?php
$baseUrl = rtrim(config("services.external_billing.url"), "/");
$customers = \App\Models\Customer::whereNotNull("external_cucode")->take(50)->get();

foreach ($customers as $customer) {
    $res = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
        "ngrok-skip-browser-warning" => "true"
    ])->asMultipart()->post("{$baseUrl}/API/announcements/bill_master.php", [
        ["name" => "from_date", "contents" => "2020-01-01"],
        ["name" => "to_date", "contents" => "2026-12-31"],
        ["name" => "cucode", "contents" => $customer->external_cucode]
    ]);

    if ($res->successful()) {
        $data = $res->json();
        if (isset($data["data"]) && count($data["data"]) > 0) {
            echo "Found " . count($data["data"]) . " bills for customer " . $customer->external_cucode . "\n";
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
            }
            break; // Stop after finding one customer with bills
        }
    }
}

