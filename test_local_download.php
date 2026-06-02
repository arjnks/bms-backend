<?php
try {
    $admin = \App\Models\User::where("role", "admin")->first();
    $token = $admin->createToken("test_admin")->plainTextToken;
    $res = \Illuminate\Support\Facades\Http::withToken($token)->timeout(30)->get("http://127.0.0.1:8000/api/v1/admin/customers/1/external-bills");
    $bills = $res->json();
    if (isset($bills["data"][0]["BILLNO"])) {
        $billNo = urlencode(str_replace("/", "-", $bills["data"][0]["BILLNO"]));
        $dl = \Illuminate\Support\Facades\Http::withToken($token)->timeout(30)->get("http://127.0.0.1:8000/api/v1/admin/customers/1/external-bills/$billNo/download");
        echo "Download URL Status: " . $dl->status() . "\n" . $dl->body();
    } else {
        echo "No bills found: " . json_encode($bills);
    }
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }

