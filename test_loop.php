<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;

$allCustomers = [];
$page = 1;
$baseUrl = "https://billing.leopharma.tech";
while (true) {
    echo "Fetching page $page...\n";
    $response = Http::timeout(60)
        ->withOptions([
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
        ])
        ->withHeaders([
            "ngrok-skip-browser-warning" => "true",
            "Connection" => "close"
        ])
        ->get("$baseUrl/API/announcements/customer_details.php", [
            "page" => $page
        ]);

    if ($response->successful()) {
        $data = $response->json();
        if (isset($data["status"]) && $data["status"] === "empty") { echo "Break: empty status\n"; break; }
        
        $batch = $data["data"] ?? [];
        if (empty($batch)) { echo "Break: empty batch\n"; break; }
        
        $allCustomers = array_merge($allCustomers, $batch);
        
        if (isset($data["total_pages"]) && $page >= $data["total_pages"]) { echo "Break: total pages reached\n"; break; }
        
        $page++;
    } else {
        echo "Break: non-success\n";
        break;
    }
}
echo "Total fetched: " . count($allCustomers) . "\n";

