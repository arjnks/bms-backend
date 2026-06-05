<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$baseUrl = config("services.external_billing.url", "https://billing.leopharma.tech");
$response = Illuminate\Support\Facades\Http::timeout(30)->withHeaders(["ngrok-skip-browser-warning" => "true"])->get(rtrim($baseUrl, "/") . "/API/announcements/bill_master_acc.php?page=1");
if ($response->successful()) {
    $json = $response->json();
    $data = $json["data"] ?? $json ?? [];
    if (!empty($data)) {
        print_r(array_keys($data[0]));
    } else {
        echo "Empty data array\n";
    }
} else {
    echo "HTTP failed: " . $response->status() . "\n";
}

