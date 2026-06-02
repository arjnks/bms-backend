<?php
try {
    $user = \App\Models\User::where("role", "customer")->first();
    $token = $user->createToken("test_cust_token")->plainTextToken;
    echo "Cust Token: " . $token . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

