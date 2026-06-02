<?php
try {
    $response = \Illuminate\Support\Facades\Http::timeout(15)->get("http://192.168.0.186:8080/API/announcements/bill_master_acc.php?page=1&cucode=013851&from_date=2024-01-01&to_date=2024-12-31");
    echo "Status: " . $response->status() . "\n";
    echo substr($response->body(), 0, 500) . "\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

