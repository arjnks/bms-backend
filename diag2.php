<?php
// Test 1: Storage writability
$tmpDir = sys_get_temp_dir();
echo "Temp dir: $tmpDir\n";
echo "Writable: " . (is_writable($tmpDir) ? "YES" : "NO") . "\n";

// Test 2: Storage disk
$storageDir = storage_path("app/public/proofs");
echo "Storage path: $storageDir\n";
echo "Storage exists: " . (file_exists($storageDir) ? "YES" : "NO") . "\n";
echo "Storage writable: " . (is_writable(storage_path("app/public")) ? "YES" : "NO") . "\n";

// Test 3: Write test
$testFile = $tmpDir . "/test_write_" . time() . ".txt";
file_put_contents($testFile, "test");
echo "Write test: " . (file_exists($testFile) ? "OK" : "FAILED") . "\n";
@unlink($testFile);

// Test 4: Bills count
echo "Bills in DB: " . \App\Models\Bill::count() . "\n";
echo "APP_URL: " . config("app.url") . "\n";

