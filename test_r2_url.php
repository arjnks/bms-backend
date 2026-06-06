<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $path = 'bills/pdf/test_file.pdf';
    echo "Attempting to generate temporary URL for: $path\n";
    $url = \Illuminate\Support\Facades\Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(15));
    echo "URL Generated successfully:\n$url\n";
} catch (\Exception $e) {
    echo "ERROR GENERATING URL:\n";
    echo get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
