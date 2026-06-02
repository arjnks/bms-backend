<?php
$customer = \App\Models\User::where("role", "customer")->first();
$token = $customer->createToken("test")->plainTextToken;
$res = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003/api/v1/customer/external-bills/LPH_2627_109319/download-url?format=excel");
echo "Status: " . $res->status() . "\n";
echo "Response: " . $res->body() . "\n";

