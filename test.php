<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    app(App\Http\Controllers\Api\V1\Admin\OverviewController::class)->index();
    echo "Success!\n";
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
}
