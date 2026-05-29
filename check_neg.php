<?php
$context = stream_context_create(["http" => ["header" => "ngrok-skip-browser-warning: true\r\n"]]);
$json = file_get_contents("https://unknowing-relight-civic.ngrok-free.dev/API/announcements/bill_master.php", false, $context);
$data = json_decode($json, true);
$neg = 0; $total = 0; $sum = 0;
foreach($data as $cucode => $bills) {
  foreach($bills as $b) {
    if (isset($b["NETAMOUNT"])) {
      $amt = (float)$b["NETAMOUNT"];
      $sum += $amt;
      if ($amt < 0) $neg++;
    }
  }
}
echo "Negative bills: $neg\nTotal sum: $sum\n";

