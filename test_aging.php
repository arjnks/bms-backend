<?php
try {
    $admin = \App\Models\User::where("role", "admin")->first();
    $token = $admin->createToken("test_admin")->plainTextToken;
    $res = \Illuminate\Support\Facades\Http::withToken($token)->get("http://127.0.0.1:8003/api/v1/admin/reports/aging");
    echo "Status: " . $res->status() . "\n";
    echo "Body: " . substr($res->body(), 0, 500) . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

