<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = rtrim(config("services.external_billing.url"), "/");
echo "URL configured in .env is: " . $url . "\n";

try {
    $response = \Illuminate\Support\Facades\Http::timeout(10)
        ->withOptions([\CURLOPT_SSL_VERIFYHOST => 0, \CURLOPT_SSL_VERIFYPEER => 0])
        ->asMultipart()
        ->post($url . "/API/announcements/bill_details.php", [
            ["name" => "billno", "contents" => "100455"]
        ]);
        
    if ($response->successful()) {
        $data = $response->json();
        if (!empty($data["data"])) {
            echo "SUCCESS! Found " . count($data["data"]) . " line items.\n";
        } else {
            echo "FAILED: API returned empty data. Response: " . $response->body() . "\n";
        }
    } else {
        echo "FAILED: HTTP " . $response->status() . " - " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "CRASH/TIMEOUT: " . $e->getMessage() . "\n";
}

