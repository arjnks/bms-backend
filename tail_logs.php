<?php
$file = "storage/logs/laravel.log";
if (file_exists($file)) {
    $lines = file($file);
    $last_lines = array_slice($lines, -50);
    foreach ($last_lines as $line) {
        if (strpos($line, "getBillDetails") !== false || strpos($line, "failed") !== false) {
            echo $line;
        }
    }
}

