<?php
use Illuminate\Support\Facades\Http;

try {
    // 1. Get an Admin Token directly from DB
    $admin = \App\Models\User::where("role", "admin")->first();
    $adminToken = $admin->createToken("test_admin")->plainTextToken;

    // 2. Get a Customer Token directly from DB
    $customerUser = \App\Models\User::where("role", "customer")->first();
    $customerToken = $customerUser->createToken("test_customer")->plainTextToken;
    $customerBill = \App\Models\Bill::where("customer_id", $customerUser->customer->id)->first();
    $adminBill = \App\Models\Bill::first();

    echo "=== TESTING ROUTE 1: Admin Live ERP ===\n";
    $url1 = "https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/customers/" . $customerUser->customer->id . "/external-bills/LPH-2627-107453/download";
    $res1 = Http::withToken($adminToken)->get($url1);
    echo "Status: " . $res1->status() . "\n";
    $json1 = $res1->json();
    if (isset($json1["download_url"])) {
        echo "download_url received: " . substr($json1["download_url"], 0, 50) . "...\n";
        $r2 = Http::get($json1["download_url"]);
        echo "R2 File Status: " . $r2->status() . " (Size: " . strlen($r2->body()) . " bytes)\n";
    } else {
        echo "FAILED: " . $res1->body() . "\n";
    }

    echo "\n=== TESTING ROUTE 2: Admin Bill Details ===\n";
    $url2 = "https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/bills/" . $adminBill->id . "/download";
    $res2 = Http::withToken($adminToken)->get($url2);
    echo "Status: " . $res2->status() . "\n";
    $json2 = $res2->json();
    if (isset($json2["download_url"])) {
        echo "download_url received: " . $json2["download_url"] . "\n";
        // Follow redirect manually if needed, or Http::get automatically follows
        $r2 = Http::withoutRedirecting()->get($json2["download_url"]);
        echo "Stream Status: " . $r2->status() . "\n";
        if ($r2->status() == 302) {
            echo "Redirects to: " . substr($r2->header("Location"), 0, 50) . "...\n";
            $final = Http::get($r2->header("Location"));
            echo "Final File Status: " . $final->status() . " (Size: " . strlen($final->body()) . " bytes)\n";
        }
    } else {
        echo "FAILED: " . $res2->body() . "\n";
    }

    echo "\n=== TESTING ROUTE 3: Customer My Bills ===\n";
    $url3 = "https://bms-backend-production-d0fe.up.railway.app/api/v1/customer/bills/" . $customerBill->id . "/download";
    $res3 = Http::withToken($customerToken)->get($url3);
    echo "Status: " . $res3->status() . "\n";
    $json3 = $res3->json();
    if (isset($json3["download_url"])) {
        echo "download_url received: " . $json3["download_url"] . "\n";
        $r2 = Http::withoutRedirecting()->get($json3["download_url"]);
        echo "Stream Status: " . $r2->status() . "\n";
        if ($r2->status() == 302) {
            echo "Redirects to: " . substr($r2->header("Location"), 0, 50) . "...\n";
            $final = Http::get($r2->header("Location"));
            echo "Final File Status: " . $final->status() . " (Size: " . strlen($final->body()) . " bytes)\n";
        }
    } else {
        echo "FAILED: " . $res3->body() . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

