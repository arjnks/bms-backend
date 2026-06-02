<?php
try {
    $billing = app(\App\Services\ExternalBillingService::class);
    $billNo = "LPH-2627-107453";
    
    echo "--- First Run (Expect slow download + upload to R2) ---\n";
    $start = microtime(true);
    $items = $billing->getBillDetails(107453);
    if (empty($items)) { echo "No items found!\n"; exit; }
    
    $path = $billing->generatePdf($items, $billNo, "2026-05-30", "Test Customer");
    echo "Time: " . round(microtime(true) - $start, 2) . "s\n";
    echo "R2 Path returned: $path\n";
    
    echo "--- Second Run (Checking cache logic manually) ---\n";
    $start = microtime(true);
    $r2Path = $billing->getCachedFilePath("pdf", $billNo);
    if (\Illuminate\Support\Facades\Storage::disk("r2")->exists($r2Path)) {
        echo "Found in cache! Time: " . round(microtime(true) - $start, 2) . "s\n";
        echo "R2 Path checked: $r2Path\n";
    } else {
        echo "Cache check failed!\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

