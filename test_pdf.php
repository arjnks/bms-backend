<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$billNo = "100907";
$items = [
    ["ITEMNAME" => "PARACETAMOL 500MG", "HSNCODE" => "3004", "QUANTITY" => 10, "FREE" => 0, "SRATE" => 10, "PMRP" => 12, "GSTRATE" => 12, "TOTALAMOUNT" => 100, "BATCHNO" => "B123", "EXPIRYDATE" => "12/25"],
    ["ITEMNAME" => "AMOXICILLIN 250MG", "HSNCODE" => "3004", "QUANTITY" => 5, "FREE" => 0, "SRATE" => 20, "PMRP" => 25, "GSTRATE" => 12, "TOTALAMOUNT" => 100, "BATCHNO" => "B124", "EXPIRYDATE" => "12/25"]
];

$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("pdf.external_bill", [
    "billNo" => $billNo,
    "billDate" => "2024-02-27 00:00:00",
    "customerName" => "SWAPNA MEDICALS - KUNNAMKULAM",
    "custPhone" => "9846790917",
    "custGst" => "32AALCA0738P1ZG",
    "items" => $items,
    "words" => "One Hundred Only",
    "netAmount" => 200,
    "qrCodeBase64" => ""
]);
$pdf->setPaper("a4", "landscape"); // Enforce landscape here too
file_put_contents("test_landscape_bill.pdf", $pdf->output());
echo "PDF generated.\n";

