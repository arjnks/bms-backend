<?php
try {
    $admin = \App\Models\User::where("role", "admin")->first();
    $token = $admin->createToken("test_admin")->plainTextToken;
    $endpoints = [
        "/api/v1/admin/reports/aging",
        "/api/v1/admin/reports/collections",
        "/api/v1/admin/overview",
        "/api/v1/admin/bills?is_overdue=true"
    ];
    foreach($endpoints as $e) {
        $res = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003" . $e);
        echo $e . " -> Status: " . $res->status() . "\n";
    }
} catch (Exception $ex) { echo $ex->getMessage(); }

