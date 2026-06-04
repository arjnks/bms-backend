<?php
$file = "storage/logs/laravel.log";
if (file_exists($file)) {
    $lines = file($file);
    $last_lines = array_slice($lines, -150);
    foreach ($last_lines as $line) {
        if (strpos(strtolower($line), "bill details") !== false || strpos(strtolower($line), "getbilldetails") !== false) {
            echo $line;
        }
    }
}

