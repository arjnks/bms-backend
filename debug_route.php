<?php
use Illuminate\Support\Facades\Route;
Route::get("/debug-pdf/{billno}", function($billno) {
    try {
        $billing = app(\App\Services\ExternalBillingService::class);
        preg_match("/(\d+)$/", $billno, $matches);
        $numericId = (int) ($matches[1] ?? $billno);
        $items = $billing->getBillDetails($numericId);
        if (empty($items)) return "No items";
        $pdfPath = $billing->generatePdf($items, $billno, $items[0]["BILLDATE"] ?? "", "Test Customer");
        return "Success: " . $pdfPath;
    } catch (\Throwable $e) {
        return "Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
    }
});

