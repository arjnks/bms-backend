<?php
use Barryvdh\DomPDF\Facade\Pdf;
$items = [
    ["PMRP" => 100, "BATCHNO" => "B123", "EXPIRYDATE" => "2027-12", "COMPNAME" => "INTAS", "ITEMNAME" => "PARACETAMOL 500MG", "HSNCODE" => "3004", "QUANTITY" => 10, "FREE" => 1, "SRATE" => 80, "DISCOUNT" => 10, "CASHDISPER" => 0, "DISVALUE" => 80, "TAXABLE" => 720, "GSTRATE" => 12, "GSTVALUE" => 86.4, "TOTALAMOUNT" => 806.4],
    ["PMRP" => 250, "BATCHNO" => "X987", "EXPIRYDATE" => "2028-05", "COMPNAME" => "CIPLA", "ITEMNAME" => "AZITHROMYCIN 250MG", "HSNCODE" => "3004", "QUANTITY" => 5, "FREE" => 0, "SRATE" => 200, "DISCOUNT" => 5, "CASHDISPER" => 0, "DISVALUE" => 50, "TAXABLE" => 950, "GSTRATE" => 18, "GSTVALUE" => 171, "TOTALAMOUNT" => 1121]
];
$data = ["items" => $items, "billNo" => "LPH/TEST/123", "billDate" => "2026-06-02", "customerName" => "TEST PHARMACY", "netAmount" => 1927.40];

foreach ([1, 2, 3] as $v) {
    $pdf = Pdf::loadView("pdf.external_bill_v{$v}", $data)->setPaper("a4", "portrait");
    $pdf->save(public_path("pdf_test_v{$v}.pdf"));
}
echo "Generated public/pdf_test_v1.pdf, v2, v3\n";

