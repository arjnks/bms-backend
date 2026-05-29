<?php
$context = stream_context_create(["http" => ["header" => "ngrok-skip-browser-warning: true\r\n"]]);
$json = file_get_contents("https://unknowing-relight-civic.ngrok-free.dev/API/announcements/bill_master.php", false, $context);
file_put_contents("api_dump.json", substr($json, 0, 500));

