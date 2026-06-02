<?php
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Bill;

$bill = Bill::with(["customer.user", "lineItems"])->latest()->first();
if (!$bill) { echo "No bill found"; exit; }

$pdf = Pdf::loadView("pdf.bill", ["bill" => $bill]);
$path = public_path("test_invoice.pdf");
file_put_contents($path, $pdf->output());
echo "PDF generated at: " . url("test_invoice.pdf");

