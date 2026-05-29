<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bill = \App\Models\Bill::find(24295);
if ($bill) {
    echo "Found Bill:\n";
    print_r($bill->toArray());
} else {
    echo "Bill 24295 NOT FOUND in local DB either.\n";
}

