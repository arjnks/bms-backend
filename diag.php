<?php
$baseUrl = rtrim(config("services.external_billing.url"), "/");
echo "ERP URL: " . $baseUrl . "\n";
echo "Bills in DB: " . \App\Models\Bill::count() . "\n";

$customer = \App\Models\Customer::whereNotNull("external_cucode")->first();
echo "Testing cucode: " . $customer->external_cucode . "\n";

$res = \Illuminate\Support\Facades\Http::timeout(10)
    ->withHeaders(["ngrok-skip-browser-warning" => "true"])
    ->asMultipart()
    ->post("$baseUrl/API/announcements/bill_master.php", [
        ["name" => "cucode",    "contents" => $customer->external_cucode],
        ["name" => "from_date", "contents" => "2023-01-01"],
        ["name" => "to_date",   "contents" => "2026-05-30"],
    ]);
echo "HTTP status: " . $res->status() . "\n";
echo "Response: " . substr($res->body(), 0, 600);

