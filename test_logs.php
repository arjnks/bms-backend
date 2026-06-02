<?php
$logs = file_get_contents("https://bms-backend-production-d0fe.up.railway.app/api/debug-logs");
echo substr($logs, -3000);

