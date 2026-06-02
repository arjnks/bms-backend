<?php
$url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
    "external.bills.stream",
    now()->addMinutes(15),
    ["billno" => "LPH-2627-107453", "format" => "excel"]
);
echo "Generated URL: " . $url . "\n";
$req = \Illuminate\Http\Request::create($url, "GET");
echo "Is Valid: " . ($req->hasValidSignature() ? "YES" : "NO") . "\n";

