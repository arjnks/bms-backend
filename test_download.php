<?php
$admin = \App\Models\User::where("role", "admin")->first();
$token = $admin->createToken("test")->plainTextToken;
$res = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003/api/v1/admin/customers/1593/external-bills/LPH_2627_109319/download?format=excel");
echo "Status: " . $res->status() . "\n";
echo "Response: " . $res->body() . "\n";

