<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$customerName = "Gold Win Medicals";
$overdueCount = 2;
$invoiceList = "Invoice No.     Amount (₹)\n----------------------------\n";
$invoiceList .= str_pad("INV-GW-101", 15) . str_pad("24,500.00", 13, ' ', STR_PAD_LEFT) . "\n";
$invoiceList .= str_pad("INV-GW-102", 15) . str_pad("12,300.00", 13, ' ', STR_PAD_LEFT) . "\n";
$invoiceList .= "----------------------------\n";
$invoiceList .= "Total Due      " . str_pad("36,800.00", 13, ' ', STR_PAD_LEFT);

// Send message
config(['services.whatsapp.api_url' => 'https://api.ultramsg.com']);
config(['services.whatsapp.phone_number_id' => 'instance94374']);
config(['services.whatsapp.access_token' => '68clewup65guncc6']);

$whatsapp = new \App\Services\WhatsAppService();
$phone = '7736728416';
$vars = [$customerName, $overdueCount, $invoiceList];

echo "Sending real simulation for {$customerName}...\n";
$success = $whatsapp->sendTemplate($phone, 'payment_reminder_v1', $vars);
echo $success ? "Success!\n" : "Failed!\n";
