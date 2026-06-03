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
        $this->info("Recent sync complete! Upserted {$insertedBills} recent bills and {$insertedLineItems} line items.");

        $this->info("Fetching historical unpaid dues from accounting ledger (all-time)...");
        $unpaidBills = $this->billing->getUnpaidBills();
        $unpaidCount = count($unpaidBills);
        $this->info("Found {$unpaidCount} historical unpaid bills. Syncing...");

        $customersByCucode = Customer::whereNotNull('external_cucode')->get()->keyBy('external_cucode');
        
        $histBar = $this->output->createProgressBar($unpaidCount);
        $histBar->start();

        $histUpserted = 0;
        $upsertData = [];
        $now = now()->format('Y-m-d H:i:s');
        
        foreach ($unpaidBills as $b) {
            $customer = $customersByCucode[$b['cucode'] ?? ''] ?? null;
            if (!$customer) {
                $histBar->advance();
                continue;
            }

            $invoiceNo = (string)($b['billno'] ?? '');
            if (!$invoiceNo) {
                $histBar->advance();
                continue;
            }

            $billDateObj = isset($b['date']) ? Carbon::parse($b['date']) : now();
            $billDate = $billDateObj->format('Y-m-d');
            $lockDays = (int) ($b['lockdays'] ?? 0);
            $dueDate = (clone $billDateObj)->addDays($lockDays)->format('Y-m-d');
            
            $netamount = (float) ($b['netamount'] ?? 0);
            $amtreceived = (float) ($b['amountrecieved'] ?? $b['amtreceived'] ?? $b['amount_received'] ?? 0);
            $isSettled = (($b['settled'] ?? 'N') === 'Y');

            $upsertData[] = [
                "customer_id" => $customer->id,
                "invoice_no" => $invoiceNo,
                "bill_date" => $billDate,
                "due_date" => $dueDate,
                "subtotal" => $netamount,
                "grand_total" => $netamount,
                "amount_received" => $amtreceived,
                "is_settled" => $isSettled,
                "aging_days" => (int) ($b['ddays'] ?? 0),
                "lock_days" => $lockDays,
                "payment_status" => $isSettled ? 'paid' : 'unpaid',
                "status" => $isSettled ? 'paid' : 'unpaid',
                "created_at" => $now,
                "updated_at" => $now,
            ];

            $histUpserted++;
            $histBar->advance();

            if (count($upsertData) >= 1000) {
                Bill::upsert($upsertData, ['customer_id', 'invoice_no'], ['bill_date', 'due_date', 'subtotal', 'grand_total', 'amount_received', 'is_settled', 'aging_days', 'lock_days', 'payment_status', 'status', 'updated_at']);
                $upsertData = [];
            }
        }
        
        if (!empty($upsertData)) {
            Bill::upsert($upsertData, ['customer_id', 'invoice_no'], ['bill_date', 'due_date', 'subtotal', 'grand_total', 'amount_received', 'is_settled', 'aging_days', 'lock_days', 'payment_status', 'status', 'updated_at']);
        }

        // Fix the 'ghost bills' issue (approx 2cr extra bug)
        // If an unsettled bill is NO LONGER returned by the API, it means it has been fully paid and dropped from the unpaid ledger.
        if (!empty($unpaidBills)) {
            $erpInvoiceNos = collect($unpaidBills)->pluck('billno')->filter()->values()->toArray();
            if (!empty($erpInvoiceNos)) {
                $missingBillsCount = Bill::where('is_settled', false)
                    ->whereNotIn('invoice_no', $erpInvoiceNos)
                    ->update([
                        'is_settled' => true,
                        'amount_received' => DB::raw('grand_total'),
                        'payment_status' => 'paid',
                        'status' => 'paid',
                        'updated_at' => now()
                    ]);
                
                if ($missingBillsCount > 0) {
                    $this->info("Marked {$missingBillsCount} missing/ghost bills as fully settled (dropped from ERP unpaid ledger).");
                }
            }
        }

        $histBar->finish();
        $this->newLine();
        $this->info("Historical sync complete! Upserted {$histUpserted} unpaid bills.");
    }
}

