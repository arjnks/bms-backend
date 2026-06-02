<?php
try {
    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        "external.bills.stream",
        now()->addMinutes(15),
        ["billno" => "LPH-2627-107453", "format" => "excel"]
    );
    echo "URL: " . $url . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

