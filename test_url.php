<?php
try {
    $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        "bills.download.stream",
        now()->addMinutes(30),
        ["id" => 1]
    );
    echo "URL: " . $url . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

