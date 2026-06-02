<?php
$billno = "LPH/2627/107453";
$url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
    "external.bills.stream",
    now()->addMinutes(15),
    ["billno" => $billno, "format" => "excel"]
);
echo "Generated: " . $url . "\n";
$req1 = \Illuminate\Http\Request::create($url, "GET");
echo "Valid raw: " . ($req1->hasValidSignature() ? "YES" : "NO") . "\n";
// simulate browser decoding %2F to /
$decodedUrl = str_replace("%2F", "/", $url);
$req2 = \Illuminate\Http\Request::create($decodedUrl, "GET");
echo "Valid decoded: " . ($req2->hasValidSignature() ? "YES" : "NO") . "\n";

