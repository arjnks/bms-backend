<?php
$content = file_get_contents("d:\internship work\BILL MANAGEMENT SYSTEM\backend\resources\views\pdf\external_bill.blade.php");
// Check the existing empty row logic
preg_match("/max\(0, (\d+) - count/", $content, $matches);
echo "Current max rows: " . ($matches[1] ?? "not found");

