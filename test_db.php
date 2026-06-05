<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$bill = \App\Models\Bill::where("invoice_no", "LPH/2526/553624")->first();
echo json_encode($bill, JSON_PRETTY_PRINT);

