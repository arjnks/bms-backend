<?php
$b = App\Models\Bill::where("invoice_no", "LPH/2627/110154")->first();
try {
    $b->lineItems()->create([
        "product_name" => "Test Product",
        "hsn_code" => "12345",
        "qty" => 1,
        "unit" => "NOS",
        "rate" => 10.5,
        "gst_pct" => 5.0,
        "line_total" => 11.0
    ]);
    echo "Saved successfully.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}

