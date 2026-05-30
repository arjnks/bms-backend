<?php
try {
    $c = app(\App\Http\Controllers\Api\V1\Admin\SyncController::class);
    $res = $c->syncCustomers(new \Illuminate\Http\Request());
    echo "Response: " . $res->getContent() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
