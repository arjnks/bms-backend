<?php
$raw = file_get_contents("https://billing.leopharma.tech/API/announcements/bill_master_acc1.php?page=1");
echo substr($raw, 0, 500) . "\n";

