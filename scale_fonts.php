<?php
$files = [
    "resources/views/pdf/external_bill.blade.php",
    "resources/views/pdf/bill.blade.php"
];

foreach ($files as $file) {
    $path = __DIR__ . "/" . $file;
    $content = file_get_contents($path);
    $content = preg_replace_callback("/font-size:\s*([\d\.]+)px/", function($m) {
        $newSize = round((float)$m[1] * 1.5, 1);
        return "font-size: {$newSize}px";
    }, $content);
    file_put_contents($path, $content);
    echo "Scaled fonts in {$file}\n";
}

