<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$statuses = \App\Models\Bill::select("status")->groupBy("status")->pluck("status");
echo json_encode($statuses);

