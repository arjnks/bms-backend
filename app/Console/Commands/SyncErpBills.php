<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Bill;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class SyncErpBills extends Command
{
    protected $signature = "erp:sync-bills {--months=120 : Months of history to fetch}";
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
                            // ERP returns inconsistent casing — try both
                            $invoiceNo = (string)($bill["BILLNO"] ?? $bill["billno"] ?? $bill["BN"] ?? $bill["bn"] ?? '');
                            if ($invoiceNo === '') continue;

                            $rawDate  = $bill["DATE"]  ?? $bill["date"]  ?? null;
                            $billDate = $rawDate ? \Carbon\Carbon::parse($rawDate) : now();
                            $lockDays = (int)($bill["lockdays"] ?? $bill["LOCKDAYS"] ?? 0);
                            $dueDate  = $billDate->copy()->addDays($lockDays);
                            $netAmount = (float)($bill["NETAMOUNT"] ?? $bill["netamount"] ?? 0);
                            $now = now()->format('Y-m-d H:i:s');
                            $billsToInsert[] = [
                                "customer_id"    => $customerId,
                                "invoice_no"     => $invoiceNo,
                                "bill_date"      => $billDate->format("Y-m-d"),
                                "due_date"       => $dueDate->format("Y-m-d"),
                                "grand_total"    => $netAmount,
                                "subtotal"       => $netAmount,
                                "gst_total"      => 0,
                                "status"         => $dueDate->isPast() ? "overdue" : "unpaid",
                                "payment_status" => "unpaid",
                                "created_at"     => $now,
                                "updated_at"     => $now,
                            ];
                        }
                    }
                }
            }

            if (!empty($billsToInsert)) {
                foreach ($billsToInsert as $b) {
                    // Use composite key: customer_id + invoice_no (invoice_no is NOT globally unique)
                    $localBill = Bill::where('customer_id', $b['customer_id'])
                        ->where('invoice_no', $b['invoice_no'])
                        ->first();
                    // Preserve payment state already submitted by customer — don't overwrite with 'unpaid'
                    if ($localBill && in_array($localBill->payment_status, ['payment_submitted', 'paid', 'proof_rejected'])) {
                        $b['payment_status'] = $localBill->payment_status;
                        $b['status']         = $localBill->status;
                    }
                    Bill::updateOrCreate(
                        ['customer_id' => $b['customer_id'], 'invoice_no' => $b['invoice_no']],
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

