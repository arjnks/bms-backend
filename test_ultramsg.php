<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Force the configuration dynamically for this test
config(['services.whatsapp.api_url' => 'https://api.ultramsg.com']);
config(['services.whatsapp.phone_number_id' => 'instance94374']);
config(['services.whatsapp.access_token' => '68clewup65guncc6']);

$whatsapp = new \App\Services\WhatsAppService();
$phone = '7736728416';

$testData = [
    'new_bill_uploaded_v1' => ['Arjun', 'INV-999', '15000.00'],
    'payment_reminder_v1' => ['Arjun', 3, "Invoice No.     Amount (₹)\n----------------------------\nINV-999          15,000.00\n----------------------------\nTotal Due        15,000.00"],
    'payment_received_v1' => ['Arjun', 'INV-999', 'HDFC123456789'],
    'payment_verified_v1' => ['Arjun', 'INV-999'],
    'payment_rejected_v1' => ['Arjun', 'INV-999', 'Blurry Screenshot'],
];

foreach ($testData as $template => $vars) {
    echo "Sending $template...\n";
    $success = $whatsapp->sendTemplate($phone, $template, $vars);
    echo $success ? "Success!\n" : "Failed!\n";
    sleep(1); // avoid rate limiting
}

echo "All tests complete.\n";
