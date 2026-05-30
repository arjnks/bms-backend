<?php
$res = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
    "ngrok-skip-browser-warning" => "true"
])->asMultipart()->post("https://unknowing-relight-civic.ngrok-free.dev/API/announcements/bill_master.php", [
    ["name" => "from_date", "contents" => "2024-01-01"],
    ["name" => "to_date", "contents" => "2024-05-30"]
]);
if ($res->successful()) {
    $data = $res->json();
    if (isset($data["data"])) {
        echo "Got " . count($data["data"]) . " bills. Sample: " . json_encode($data["data"][0]);
    } else {
        echo "Response: " . json_encode($data);
    }
} else {
    echo "HTTP Error: " . $res->status();
}

