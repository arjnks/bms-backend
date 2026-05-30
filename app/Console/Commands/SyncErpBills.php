<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Bill;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class SyncErpBills extends Command
{
    protected $signature = "erp:sync-bills {--months=12 : Months of history to fetch}";
    protected $description = "Sync bills from ERP to local database for all customers";

    public function handle()
    {
        $months = (int) $this->option("months");
        $fromDate = now()->subMonths($months)->format("Y-m-d");
        $toDate = now()->format("Y-m-d");
        $baseUrl = rtrim(config("services.external_billing.url"), "/");
        
        if (empty($baseUrl)) {
            $this->error("EXTERNAL_BILLING_URL is not set.");
            return 1;
        }

        $customers = Customer::whereNotNull("external_cucode")->get();
        $this->info("Fetching bills for {$customers->count()} customers from {$fromDate} to {$toDate}...");

        $chunks = $customers->chunk(20);
        $totalSynced = 0;

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl, $fromDate, $toDate) {
                return $chunk->map(function ($customer) use ($pool, $baseUrl, $fromDate, $toDate) {
                    return $pool->as((string)$customer->id)->timeout(15)->withHeaders([
                        "ngrok-skip-browser-warning" => "true"
                    ])->asMultipart()->post("{$baseUrl}/API/announcements/bill_master.php", [
                        ["name" => "cucode",    "contents" => $customer->external_cucode],
                        ["name" => "from_date", "contents" => $fromDate],
                        ["name" => "to_date",   "contents" => $toDate],
                    ]);
                });
            });

            $billsToInsert = [];
            foreach ($responses as $customerId => $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $data = $response->json();
                    if (isset($data["status"]) && $data["status"] === "success" && isset($data["data"])) {
                        foreach ($data["data"] as $bill) {
                            if (!isset($bill["BILLNO"]) && !isset($bill["BN"])) continue;
                            
                            $billDate = isset($bill["DATE"]) ? \Carbon\Carbon::parse($bill["DATE"]) : now();
                            $billsToInsert[] = [
                                "customer_id" => $customerId,
                                "invoice_no" => $bill["BILLNO"] ?? $bill["BN"],
                                "bill_date" => $billDate->format("Y-m-d"),
                                "due_date" => $billDate->copy()->addDays(30)->format("Y-m-d"),
                                "grand_total" => $bill["NETAMOUNT"] ?? 0,
                                "subtotal" => $bill["NETAMOUNT"] ?? 0,
                                "gst_total" => 0,
                                "status" => $billDate->copy()->addDays(30)->isPast() ? "overdue" : "unpaid",
                                "payment_status" => "unpaid",
                            ];
                        }
                    }
                }
            }

            if (!empty($billsToInsert)) {
                foreach ($billsToInsert as $b) {
                    Bill::updateOrCreate(
                        ["invoice_no" => $b["invoice_no"]],
                        $b
                    );
                }
                $totalSynced += count($billsToInsert);
                $this->info("Synced " . count($billsToInsert) . " bills in this batch.");
            }
        }

        $this->info("Done! Total bills synced: {$totalSynced}");
        return 0;
    }
}

