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
        $baseUrl = rtrim(config('services.external_billing.url', 'https://unknowing-relight-civic.ngrok-free.dev'), '/');
        
        $page = 1;
        $totalSynced = 0;
        $allSyncedBillNos = [];
        
        while (true) {
            $this->info("Fetching page {$page}...");
            
            try {
                $response = Http::timeout(60)
                    ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                    ->get("{$baseUrl}/API/announcements/bill_master_acc.php", ['page' => $page]);

                if (!$response->successful()) {
                    $this->error("Failed to fetch page {$page}. HTTP " . $response->status());
                    Log::error("ERP Sync failed at page {$page}", ['status' => $response->status()]);
                    break;
                }

                $json = $response->json();
                
                // If it's returning {"status": "success", "data": [...]} or just [...]
                $data = $json['data'] ?? $json ?? [];
                
                if (empty($data)) {
                    $this->info("No more data found on page {$page}. Sync complete.");
                    break;
                }
                
                $records = [];
                foreach ($data as $row) {
                    // Make sure we have a billno before trying to upsert
                    if (empty($row['billno'])) {
                        continue;
                    }
                    
                    $records[] = [
                        'billno'      => (string) $row['billno'],
                        'date'        => isset($row['date']) ? date('Y-m-d H:i:s', strtotime($row['date'])) : null,
                        'cucode'      => (string) ($row['cucode'] ?? ''),
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
                    // Bulk upsert the chunk
                    // We use billno as the unique key to match on, and update all other columns
                    ErpBillStatus::upsert(
                        $records,
                        ['billno'],
                        ['date', 'cucode', 'cuname', 'netamount', 'amtreceived', 'settled', 'ddays', 'lockdays', 'updated_at']
                    );
                    
                    foreach ($records as $rec) {
                        $allSyncedBillNos[] = $rec['billno'];
                    }

                    $count = count($records);
                    $totalSynced += $count;
                    $this->info("Upserted {$count} records from page {$page}. (Total: {$totalSynced})");
                }
                
                $page++;
                
            } catch (\Exception $e) {
                $this->error("Exception on page {$page}: " . $e->getMessage());
                Log::error("ERP Sync exception on page {$page}", ['error' => $e->getMessage()]);
                break;
            }
        } // End of while(true) loop

        // Ghost bill cleanup removed. The ERP API truncates at 50,000 records.
        
        $this->info("Sync finished. Total records synced: {$totalSynced}");
    }
}
