<?php
try {
    $content = "test content";
    \Illuminate\Support\Facades\Storage::disk("r2")->put("test.txt", $content);
    echo "URL: " . \Illuminate\Support\Facades\Storage::disk("r2")->url("test.txt") . "\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

