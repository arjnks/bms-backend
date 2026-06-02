<?php
$customer = \App\Models\User::where("role", "customer")->first();
$token = $customer->createToken("test")->plainTextToken;

$res1 = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003/api/v1/customer/external-bills/LPH_2627_109319/download-url?format=excel");
$url = json_decode($res1->body(), true)["download_url"];

$res2 = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003" . $url);
echo "Status: " . $res2->status() . "\n";
echo "Response: " . substr($res2->body(), 0, 500) . "\n";

