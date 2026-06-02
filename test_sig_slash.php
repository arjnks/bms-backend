<?php
$billno = "LPH/2627/107453";
$url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
    "external.bills.stream",
    now()->addMinutes(15),
    ["billno" => $billno, "format" => "excel"]
);
echo "Generated URL: " . $url . "\n";
// Create request with exact generated URL
$req1 = \Illuminate\Http\Request::create($url, "GET");
echo "Valid encoded: " . ($req1->hasValidSignature() ? "YES" : "NO") . "\n";
// Create request with decoded slashes (as apache/nginx sometimes rewrite)
$urlDecoded = str_replace("%2F", "/", $url);
$req2 = \Illuminate\Http\Request::create($urlDecoded, "GET");
echo "Valid decoded: " . ($req2->hasValidSignature() ? "YES" : "NO") . "\n";

