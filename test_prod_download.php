<?php
use Illuminate\Support\Facades\Http;
try {
    $adminUrl = "https://bms-backend-production-d0fe.up.railway.app/api/v1/auth/login";
    $login = Http::post($adminUrl, ["email" => "admin@leogroup.com", "password" => "password"]);
    $token = $login->json()["token"];

    echo "Fetching customer bill from live ERP...\n";
    $listUrl = "https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/customers/1593/external-bills";
    $bills = Http::withToken($token)->get($listUrl)->json();
    
    if (isset($bills["data"][0]["BILLNO"])) {
        $billNo = $bills["data"][0]["BILLNO"];
        // URL encode billno because it contains slashes like LPH/2627/107453
        $encodedBillNo = urlencode(str_replace("/", "-", $billNo));
        $downloadUrl = "https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/customers/1593/external-bills/" . $encodedBillNo . "/download";
        
        echo "Testing download endpoint: $downloadUrl\n";
        $dl = Http::withToken($token)->timeout(70)->get($downloadUrl);
        echo "Status: " . $dl->status() . "\n";
        echo "Response: " . substr($dl->body(), 0, 500) . "\n";
    } else {
        echo "Failed to get bills from ERP. Ngrok might be down.\n";
        echo json_encode($bills);
    }
} catch (Exception $e) { echo $e->getMessage(); }

