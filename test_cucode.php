<?php
$cucode = "1593"; // The user used 1593 in task-9375!
try {
    $res = Invoke-RestMethod -Uri "https://billing.leopharma.tech/API/announcements/bill_master.php" -Method Post -Body @{cucode=$cucode; from_date="2026-05-01"; to_date="2026-06-01"} -Headers @{"ngrok-skip-browser-warning"="true"}
    $res | ConvertTo-Json -Depth 5
} catch { echo $_.Exception.Message }

