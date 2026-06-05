<?php
$context = stream_context_create(["http" => ["header" => "ngrok-skip-browser-warning: true\r\n"]]);
$json = file_get_contents("https://billing.leopharma.tech/API/announcements/bill_master.php", false, $context);
file_put_contents("api_dump.json", substr($json, 0, 500));

