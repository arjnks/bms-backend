<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bills = App\Models\Bill::where("bill_file_type", "pdf")->get();
$count = 0;
foreach ($bills as $bill) {
    if (!$bill->bill_file_url) continue;
    $path = ltrim($bill->bill_file_url, "/");
    if (str_starts_with($path, "storage/")) $path = substr($path, strlen("storage/"));
    
    // Only regenerate if it is a local file
    if (!preg_match("/^https?:\/\//i", $path) && Illuminate\Support\Facades\Storage::disk("public")->exists($path)) {
        $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView("pdf.bill", ["bill" => $bill])->setPaper("a4", "landscape");
        Illuminate\Support\Facades\Storage::disk("public")->put($path, $pdf->output());
        $count++;
    }
}
echo "Regenerated {$count} local internal PDFs.";

