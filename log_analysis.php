<?php
$file = "storage/logs/laravel.log";
if (!file_exists($file)) die("No log file");
$contents = file_get_contents($file);
preg_match_all("/production\.ERROR: (.*?) {/", $contents, $matches);
$errors = array_count_values($matches[1]);
arsort($errors);
echo json_encode(array_slice($errors, 0, 10), JSON_PRETTY_PRINT);

