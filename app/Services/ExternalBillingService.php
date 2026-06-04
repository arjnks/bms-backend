<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Barryvdh\DomPDF\Facade\Pdf;

class ExternalBillingService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.external_billing.url', 'https://billing.leopharma.tech'), '/');
    }

    /**
     * Fetch all customers from external API.
     */
    public function getCustomers(): array
    {
        $allCustomers = [];
        $page = 1;

        try {
            while (true) {
                $response = Http::timeout(60)
                    ->withOptions([
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_FORBID_REUSE => true,
                        CURLOPT_FRESH_CONNECT => true,
                    ])
                    ->withHeaders([
                        'ngrok-skip-browser-warning' => 'true',
                        'Connection' => 'close'
                    ])
                    ->get("{$this->baseUrl}/API/announcements/customer_details.php", [
                        'page' => $page
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['status']) && $data['status'] === 'empty') {
                        break;
                    }
                    
                    $batch = $data['data'] ?? (is_array($data) && !isset($data['status']) ? $data : []);
                    if (empty($batch)) {
                        break;
                    }
                    
                    $allCustomers = array_merge($allCustomers, $batch);
                    
                    // If the API provides pagination metadata
                    if (isset($data['total_pages']) && $page >= $data['total_pages']) {
                        break;
                    }
                    
                    $page++;
                } else {
                    Log::error("ExternalBillingService::getCustomers ERP returned non-success on page {$page}");
                    break;
                }
            }
            return $allCustomers;
        } catch (\Exception $e) {
            Log::error('ExternalBillingService::getCustomers failed', ['error' => $e->getMessage()]);
        }
        return $allCustomers;
    }

    /**
     * Fetch bills for a customer within a date range.
     */
    public function getBills(string $cucode, string $fromDate, string $toDate): array
    {
        try {
            $response = Http::timeout(60)
                ->withOptions([
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ])
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->asMultipart()
                ->post("{$this->baseUrl}/API/announcements/bill_master.php", [
                    ['name' => 'cucode', 'contents' => $cucode],
                    ['name' => 'from_date', 'contents' => $fromDate],
                    ['name' => 'to_date', 'contents' => $toDate]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'empty') {
                    return [];
                }
                return $data['data'] ?? (is_array($data) && !isset($data['status']) ? $data : []);
            }
        } catch (\Exception $e) {
            Log::error("ExternalBillingService::getBills failed for $cucode", ['error' => $e->getMessage()]);
        }
        return [];
    }

    public function getBillDetails(string $billNo): array
    {
        \Illuminate\Support\Facades\Log::info("getBillDetails called for: " . $billNo);
        // The ERP API throws an SQL error if we pass the full string (e.g. LPH/2627/96609)
        // It expects only the numeric ID.
        preg_match('/(\d+)$/', $billNo, $matches);
        $numericId = $matches[1] ?? $billNo;

        try {
            $response = Http::timeout(60)
                ->withOptions([
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ])
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->asMultipart()
                ->post("{$this->baseUrl}/API/announcements/bill_details.php", [
                    ['name' => 'billno', 'contents' => (string)$numericId]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'empty') {
                    return [];
                }
                return $data['data'] ?? (is_array($data) && !isset($data['status']) ? $data : []);
            } else {
                Log::error("ExternalBillingService::getBillDetails ERP returned non-success for $billNo", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("ExternalBillingService::getBillDetails failed for $billNo", ['error' => $e->getMessage()]);
        }
        return [];
    }

    /**
     * Fetch unpaid historical dues across all pages.
     * @deprecated Use streamUnpaidBills() for memory-efficient processing.
     */
    public function getUnpaidBills(): array
    {
        $allBills = [];
        foreach ($this->streamUnpaidBills() as $batch) {
            foreach ($batch as $bill) {
                $allBills[] = $bill;
            }
        }
        return $allBills;
    }

    /**
     * Stream unpaid historical dues page-by-page as a generator.
     * Yields one page's worth of records at a time to avoid memory exhaustion.
     * Use this for large datasets (100k+ records).
     *
     * @return \Generator yields array[] — one page of bill records per iteration
     */
    public function streamUnpaidBills(): \Generator
    {
        $page = 1;

        try {
            while (true) {
                $response = Http::timeout(90)
                    ->withOptions([
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_FORBID_REUSE => true,
                        CURLOPT_FRESH_CONNECT => true,
                    ])
                    ->withHeaders([
                        'ngrok-skip-browser-warning' => 'true',
                        'Connection' => 'close'
                    ])
                    ->get("{$this->baseUrl}/API/announcements/bill_master_acc1.php", [
                        'page' => $page
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['status']) && $data['status'] === 'empty') {
                        break;
                    }

                    $batch = $data['data'] ?? [];
                    if (empty($batch)) {
                        break;
                    }

                    // Yield one page at a time — caller processes it, then GC can free it
                    yield $batch;

                    if (count($batch) < ($data['per_page'] ?? 50000)) {
                        break;
                    }

                    $page++;
                } else {
                    Log::error("ExternalBillingService::streamUnpaidBills ERP returned non-success on page $page", [
                        'status' => $response->status(),
                        'body'   => $response->body()
                    ]);
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::error("ExternalBillingService::streamUnpaidBills failed", ['error' => $e->getMessage()]);
        }
    }

    public function getCachedFilePath(string $format, string $billNo): string
    {
        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $ext = strtolower($format) === 'excel' ? 'xlsx' : (strtolower($format) === 'csv' ? 'csv' : 'pdf');
        $dir = $ext === 'pdf' ? 'pdf_v2' : $ext;
        return "bills/{$dir}/bill_{$safeBillNo}.{$ext}";
    }

    /**
     * Generate an Excel (.xlsx) file from bill line items.
     * Returns the file path (temp file).
     */
    public function generateExcel(array $items, string $billNo, string $billDate): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bill');

        // Column headers
        $headers = [
            'A' => 'BILL NO', 'B' => 'BILL DATE', 'C' => 'COMPANY', 'D' => 'ITEM CODE',
            'E' => 'ITEM NAME', 'F' => 'PACKING', 'G' => 'BATCH NO', 'H' => 'EXP DT',
            'I' => 'QTY', 'J' => 'FREE', 'K' => 'PTR', 'L' => 'MRP', 'M' => 'AMOUNT',
            'N' => 'SCH.DIS%', 'O' => 'DISCOUNT', 'P' => 'DIS AMT', 'Q' => 'TAXABLE AMT',
            'R' => 'GST %', 'S' => 'GST AMT', 'T' => 'VALUE', 'U' => 'NET AMOUNT', 'V' => 'HSNCODE'
        ];

        $row = 1;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }

        $sheet->getStyle("A1:V1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D3D3D3']],
        ]);

        $netAmount = $items[0]['NETAMOUNT'] ?? 0;

        $row = 2;
        foreach ($items as $item) {
            $qty = $item['QUANTITY'] ?? 0;
            $ptr = $item['SRATE'] ?? 0;
            $amount = round($qty * $ptr, 2);

            $expiry = '';
            if (!empty($item['EXPIRYDATE'])) {
                $expiry = \Carbon\Carbon::parse($item['EXPIRYDATE'])->format('d-m-Y');
            }

            $sheet->setCellValue("A{$row}", $billNo);
            $sheet->setCellValue("B{$row}", $billDate);
            $sheet->setCellValue("C{$row}", $item['COMPNAME'] ?? '');
            $sheet->setCellValue("D{$row}", $item['ITEMCODE'] ?? '');
            $sheet->setCellValue("E{$row}", $item['ITEMNAME'] ?? '');
            $sheet->setCellValue("F{$row}", $item['PACKING'] ?? '');
            $sheet->setCellValue("G{$row}", $item['BATCHNO'] ?? '');
            $sheet->setCellValue("H{$row}", $expiry);
            $sheet->setCellValue("I{$row}", $qty);
            $sheet->setCellValue("J{$row}", $item['FREE'] ?? 0);
            $sheet->setCellValue("K{$row}", $ptr);
            $sheet->setCellValue("L{$row}", $item['PMRP'] ?? 0);
            $sheet->setCellValue("M{$row}", $amount);
            $sheet->setCellValue("N{$row}", $item['SCHDISPER'] ?? 0);
            $sheet->setCellValue("O{$row}", $item['DISCOUNT'] ?? ($item['CASHDISPER'] ?? 0));
            $sheet->setCellValue("P{$row}", $item['DISVALUE'] ?? 0);
            $sheet->setCellValue("Q{$row}", $item['TAXABLE'] ?? 0);
            $sheet->setCellValue("R{$row}", $item['GSTRATE'] ?? 0);
            $sheet->setCellValue("S{$row}", $item['GSTVALUE'] ?? 0);
            $sheet->setCellValue("T{$row}", $item['TOTALAMOUNT'] ?? 0);
            $sheet->setCellValue("U{$row}", $netAmount);
            $sheet->setCellValue("V{$row}", $item['HSNCODE'] ?? '');
            
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'V') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $tmpPath = sys_get_temp_dir() . "/bill_{$safeBillNo}_" . time() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        
        $r2Path = $this->getCachedFilePath('excel', $billNo);
        \Illuminate\Support\Facades\Storage::disk('r2')->putFileAs('bills/excel', new \Illuminate\Http\File($tmpPath), basename($r2Path));
        @unlink($tmpPath);
        
        return $r2Path;
    }

    /**
     * Generate a CSV file from bill line items.
     */
    public function generateCsv(array $items, string $billNo, string $billDate = ''): string
    {
        if (empty($billDate)) {
            $billDate = \Carbon\Carbon::now()->format('d-m-Y');
        } else {
            $billDate = \Carbon\Carbon::parse($billDate)->format('d-m-Y');
        }

        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $tmpPath = sys_get_temp_dir() . "/bill_{$safeBillNo}_" . time() . '.csv';
        $fp = fopen($tmpPath, 'w');

        // UTF-8 BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, [
            'BILL NO', 'BILL DATE', 'COMPANY', 'ITEM CODE', 'ITEM NAME', 'PACKING', 'BATCH NO', 'EXP DT',
            'QTY', 'FREE', 'PTR', 'MRP', 'AMOUNT', 'SCH.DIS%', 'DISCOUNT', 'DIS AMT', 'TAXABLE AMT',
            'GST %', 'GST AMT', 'VALUE', 'NET AMOUNT', 'HSNCODE'
        ]);

        $netAmount = $items[0]['NETAMOUNT'] ?? 0;

        foreach ($items as $item) {
            $qty = $item['QUANTITY'] ?? 0;
            $ptr = $item['SRATE'] ?? 0;
            $amount = round($qty * $ptr, 2);

            $expiry = '';
            if (!empty($item['EXPIRYDATE'])) {
                $expiry = \Carbon\Carbon::parse($item['EXPIRYDATE'])->format('d-m-Y');
            }

            fputcsv($fp, [
                $billNo,
                $billDate,
                $item['COMPNAME'] ?? '',
                $item['ITEMCODE'] ?? '',
                $item['ITEMNAME'] ?? '',
                $item['PACKING'] ?? '',
                $item['BATCHNO'] ?? '',
                $expiry,
                $qty,
                $item['FREE'] ?? 0,
                $ptr,
                $item['PMRP'] ?? 0,
                $amount,
                $item['SCHDISPER'] ?? 0,
                $item['DISCOUNT'] ?? ($item['CASHDISPER'] ?? 0),
                $item['DISVALUE'] ?? 0,
                $item['TAXABLE'] ?? 0,
                $item['GSTRATE'] ?? 0,
                $item['GSTVALUE'] ?? 0,
                $item['TOTALAMOUNT'] ?? 0,
                $netAmount,
                $item['HSNCODE'] ?? '',
            ]);
        }

        fclose($fp);
        
        $r2Path = $this->getCachedFilePath('csv', $billNo);
        \Illuminate\Support\Facades\Storage::disk('r2')->putFileAs('bills/csv', new \Illuminate\Http\File($tmpPath), basename($r2Path));
        @unlink($tmpPath);
        
        return $r2Path;
    }

    /**
     * Generate a PDF invoice from bill line items.
     */
    public function generatePdf(array $items, string $billNo, string $billDate, string $customerName): string
    {
        $netAmount = $items[0]['NETAMOUNT'] ?? 0;
        
        // Generate UPI Payment String
        $upiId = env('BUSINESS_UPI_ID', 'placeholder@upi');
        $payeeName = env('BUSINESS_PAYEE_NAME', 'Leo Pharma');
        $upiString = "upi://pay?pa={$upiId}&pn=" . urlencode($payeeName) . "&am={$netAmount}&cu=INR&tn=Inv_{$billNo}";
        
        // Generate QR Code if package is installed
        $qrCodeBase64 = '';
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)->generate($upiString);
            $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        }

        $pdf = Pdf::loadView('pdf.external_bill', compact('items', 'billNo', 'billDate', 'customerName', 'netAmount', 'qrCodeBase64'))
            ->setPaper('a4', 'landscape');

        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $tmpPath = sys_get_temp_dir() . "/bill_{$safeBillNo}_" . time() . '.pdf';
        $pdf->save($tmpPath);
        
        $r2Path = $this->getCachedFilePath('pdf', $billNo);
        $folder = dirname($r2Path);
        \Illuminate\Support\Facades\Storage::disk('r2')->putFileAs($folder, new \Illuminate\Http\File($tmpPath), basename($r2Path));
        @unlink($tmpPath);
        
        return $r2Path;
    }
}

