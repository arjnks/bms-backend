<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$srv = app(App\Services\ExternalBillingService::class);
echo json_encode(array_slice($srv->getBills("050434", "2024-01-01", "2026-01-01"), 0, 1));

