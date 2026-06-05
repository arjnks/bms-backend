<?php 
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); 
$kernel->bootstrap(); 

$billingService = app()->make(App\Services\ExternalBillingService::class); 

try {
    $items = $billingService->getBillDetails('96609');
    $localPath = $billingService->generateExcel($items, 'LPH/2627/96609', '2026-05-25');
    
    $fileContents = file_get_contents($localPath);
    Illuminate\Support\Facades\Storage::disk('r2')->put('test_excel.xlsx', $fileContents, [
        'ContentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
    @unlink($localPath);
    
    $url = Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl('test_excel.xlsx', now()->addMinutes(15));
    echo "SUCCESS: " . $url . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
