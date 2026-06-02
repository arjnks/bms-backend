<?php
try {
    $response = \Illuminate\Support\Facades\Http::timeout(30)->asMultipart()->post("http://43.204.148.79/API/announcements/bill_master.php", [ ["name" => "cucode", "contents" => "013851"], ["name" => "from_date", "contents" => "2024-01-01"], ["name" => "to_date", "contents" => "2024-12-31"] ]);
    echo substr($response->body(), 0, 1000) . "\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

