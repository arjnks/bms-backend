<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ErpBillStatus;
use Illuminate\Support\Facades\DB;

class SyncErpBillStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:sync-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs ERP bill payment statuses and lockdays into a local cache table for quick lookups.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting ERP Bill Status sync...');
        $baseUrl = rtrim(config('services.external_billing.url', 'https://billing.leopharma.tech'), '/');
        
        // The ERP API now requires `cucode` via POST. We must fetch all customers with a cucode.
        $customers = \App\Models\Customer::whereNotNull('external_cucode')->where('external_cucode', '!=', '')->get();
        $this->info("Found {$customers->count()} customers with external_cucode to sync.");

        $totalSynced = 0;
        
        foreach ($customers as $index => $customer) {
            $cucode = $customer->external_cucode;
            $this->info("Syncing customer " . ($index + 1) . "/{$customers->count()} (cucode: {$cucode})...");
            
            $page = 1;
            while (true) {
                try {
                    $response = Http::timeout(60)
                        ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                        ->asMultipart()
                        ->post("{$baseUrl}/API/announcements/bill_master_acc1.php?page={$page}", [
                            'cucode' => $cucode
                        ]);

                    if (!$response->successful()) {
                        $this->error("Failed to fetch page {$page} for {$cucode}. HTTP " . $response->status());
                        Log::error("ERP Sync failed", ['cucode' => $cucode, 'page' => $page, 'status' => $response->status()]);
                        break;
                    }

                    $json = $response->json();
                    
                    // If the API returns an error for this customer
                    if (isset($json['status']) && $json['status'] === 'error') {
                        $this->error("API Error for {$cucode}: " . ($json['message'] ?? 'Unknown error'));
                        break;
                    }

                    $data = $json['data'] ?? $json ?? [];
                    
                    if (empty($data)) {
                        break;
                    }
                    
                    $records = [];
                    foreach ($data as $row) {
                        $bn = $row['billno'] ?? $row['BN'] ?? $row['BILLNO'] ?? null;
                        if (empty($bn)) {
                            continue;
                        }
                        
                        $records[] = [
                            'billno'      => (string) $bn,
                            'date'        => isset($row['date']) ? date('Y-m-d H:i:s', strtotime($row['date'])) : null,
                            'cucode'      => (string) ($row['cucode'] ?? $cucode),
                            'cuname'      => (string) ($row['cuname'] ?? ''),
                            'netamount'   => (float) ($row['netamount'] ?? 0),
                            'amtreceived' => (float) ($row['amountrecieved'] ?? $row['amtreceived'] ?? $row['amount_received'] ?? 0),
                            'settled'     => (string) ($row['settled'] ?? 'N'),
                            'ddays'       => (int) ($row['ddays'] ?? 0),
                            'lockdays'    => (int) ($row['lockdays'] ?? 0),
                            'updated_at'  => now(),
                        ];
                    }
                    
                    if (!empty($records)) {
                        $chunks = array_chunk($records, 1000);
                        foreach ($chunks as $chunk) {
                            ErpBillStatus::upsert(
                                $chunk,
                                ['billno'],
                                ['date', 'cucode', 'cuname', 'netamount', 'amtreceived', 'settled', 'ddays', 'lockdays', 'updated_at']
                            );
                        }

                        $count = count($records);
                        $totalSynced += $count;
                    }
                    
                    $page++;
                    
                } catch (\Exception $e) {
                    $this->error("Exception for {$cucode} on page {$page}: " . $e->getMessage());
                    Log::error("ERP Sync exception", ['cucode' => $cucode, 'page' => $page, 'error' => $e->getMessage()]);
                    break;
                }
            }
        }
        
        $this->info("Sync finished. Total records synced: {$totalSynced}");
    }
}
