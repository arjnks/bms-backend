<?php
use Illuminate\Support\Facades\Http;
try {
    $login = Http::post("https://bms-backend-production-d0fe.up.railway.app/api/v1/auth/login", ["email" => "admin@leogroup.com", "password" => "password"]);
    $token = $login->json()["token"];

    $res = Http::withToken($token)->get("https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/customers");
    $customers = $res->json()["data"] ?? [];
    
    $validCusts = array_filter($customers, fn($c) => !empty($c["external_cucode"]));
    echo "Live DB Customers with external_cucode: " . count($validCusts) . "\n";
    foreach(array_slice($validCusts, 0, 5) as $c) {
        echo "Customer ID: {$c['id']}, Cucode: {$c['external_cucode']}\n";
        
        $billsRes = Http::withToken($token)->get("https://bms-backend-production-d0fe.up.railway.app/api/v1/admin/customers/{$c['id']}/external-bills");
        if ($billsRes->successful() && !empty($billsRes->json()["data"])) {
            echo "BILLS FOUND!\n";
            echo json_encode($billsRes->json()["data"][0], JSON_PRETTY_PRINT) . "\n";
            break;
        }
    }
} catch (Exception $e) { echo $e->getMessage(); }
