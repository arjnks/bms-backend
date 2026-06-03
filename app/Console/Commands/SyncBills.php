<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Bill;
use App\Models\BillLineItem;
use App\Services\ExternalBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBills extends Command
{
    protected $signature = "bms:sync-bills {--test-cucode= : Test sync for a specific customer code} {--days=3650 : Number of past days to sync}";
    protected $description = "Iterate over all customers and fetch their bills using the date range filters";

    protected ExternalBillingService $billing;

    public function __construct(ExternalBillingService $billing)
    {
        parent::__construct();
        $this->billing = $billing;
    }

    public function handle()
    {
        $testCucode = $this->option("test-cucode");
        $days = (int) $this->option("days");
        
        $fromDate = Carbon::today()->subDays($days)->format("Y-m-d");
        $toDate = Carbon::today()->format("Y-m-d");

        if ($testCucode) {
            $this->info("Running test sync for cucode: {$testCucode} from {$fromDate} to {$toDate}");
            $customers = Customer::where("external_cucode", $testCucode)->get();
        } else {
            $this->info("Running full sync for all linked customers from {$fromDate} to {$toDate}");
            $customers = Customer::whereNotNull("external_cucode")->get();
        }

        $totalCustomers = $customers->count();
        $this->info("Found {$totalCustomers} customers to sync.");

        $bar = $this->output->createProgressBar($totalCustomers);
        $bar->start();

        $insertedBills = 0;
        $insertedLineItems = 0;

        foreach ($customers as $customer) {
            $cucode = $customer->external_cucode;
            
            $billsData = $this->billing->getBills($cucode, $fromDate, $toDate);

            if (empty($billsData)) {
                $bar->advance();
                continue;
            }

            foreach ($billsData as $b) {
                $invoiceNo = $b["BN"] ?? $b["BILLNO"] ?? null;
                if (!$invoiceNo) continue;

                $billDate = isset($b["DATE"]) ? Carbon::parse($b["DATE"])->format("Y-m-d") : null;
                $dueDate = isset($b["DUEDATE"]) ? Carbon::parse($b["DUEDATE"])->format("Y-m-d") : $billDate;
                $netAmount = (float)($b["NETAMOUNT"] ?? 0);

                $bill = Bill::updateOrCreate(
                    [
                        "customer_id" => $customer->id,
                        "invoice_no" => $invoiceNo,
                    ],
                    [
                        "bill_date" => $billDate,
                        "due_date" => $dueDate,
                        "subtotal" => $netAmount,
                        "gst_total" => 0,
                        "grand_total" => $netAmount,
                    ]
                );

                if ($bill->wasRecentlyCreated && !$bill->payment_status) {
                    $bill->payment_status = "unpaid";
                    $bill->status = "unpaid";
                    $bill->save();
                }

                $insertedBills++;
                usleep(50000); // 50ms

                $billnoInt = (int) ($b["BILLNO"] ?? 0);
                if ($billnoInt > 0) {
                    $itemsData = $this->billing->getBillDetails($billnoInt);
                    if (!empty($itemsData)) {
                        $lineItemsData = [];
                        foreach ($itemsData as $item) {
                            $lineItemsData[] = [
                                "bill_id" => $bill->id,
                                "product_name" => $item["ITEMNAME"] ?? "Unknown",
                                "hsn_code" => $item["HSNCODE"] ?? null,
                                "qty" => (float)($item["QUANTITY"] ?? 1),
                                "unit" => $item["PACKING"] ?? null,
                                "rate" => (float)($item["SRATE"] ?? 0),
                                "gst_pct" => (float)($item["GSTRATE"] ?? 0),
                                "line_total" => (float)($item["TOTALAMOUNT"] ?? 0),
                                "created_at" => now(),
                                "updated_at" => now(),
                            ];
                        }
                        BillLineItem::where("bill_id", $bill->id)->delete();
                        BillLineItem::insert($lineItemsData);
                        $insertedLineItems += count($lineItemsData);
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Sync complete! Upserted {$insertedBills} bills and {$insertedLineItems} line items.");
    }
}

