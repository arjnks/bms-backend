<?php
try {
    $response = \Illuminate\Support\Facades\Http::timeout(15)->asMultipart()->post("http://192.168.0.186:8080/API/announcements/bill_master_acc.php?page=1", [ ["name" => "cucode", "contents" => "013851"], ["name" => "from_date", "contents" => "2024-01-01"], ["name" => "to_date", "contents" => "2024-12-31"] ]);
    echo "Status: " . $response->status() . "\n";
    echo substr($response->body(), 0, 500) . "\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

