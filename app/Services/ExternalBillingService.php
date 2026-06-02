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
        $this->baseUrl = rtrim(config('services.external_billing.url', 'https://unknowing-relight-civic.ngrok-free.dev'), '/');
    }

    /**
     * Fetch all customers from external API.
     */
    public function getCustomers(): array
    {
        try {
            $response = Http::timeout(60)
                ->withOptions([
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ])
                ->withHeaders([
                    'ngrok-skip-browser-warning' => 'true'
                ])
                ->get("{$this->baseUrl}/API/announcements/customer_details.php");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'empty') {
                    return [];
                }
                return $data['data'] ?? (is_array($data) && !isset($data['status']) ? $data : []);
            }
        } catch (\Exception $e) {
            Log::error('ExternalBillingService::getCustomers failed', ['error' => $e->getMessage()]);
        }
        return [];
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

    public function getCachedFilePath(string $format, string $billNo): string
    {
        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $ext = $format === 'excel' ? 'xlsx' : $format;
        return "bills/{$format}/bill_{$safeBillNo}.{$ext}";
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
        Storage::disk('r2')->putFileAs('bills/excel', new \Illuminate\Http\File($tmpPath), "bill_{$safeBillNo}.xlsx");
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
        Storage::disk('r2')->putFileAs('bills/csv', new \Illuminate\Http\File($tmpPath), "bill_{$safeBillNo}.csv");
        @unlink($tmpPath);
        
        return $r2Path;
    }

    /**
     * Generate a PDF invoice from bill line items.
     */
    public function generatePdf(array $items, string $billNo, string $billDate, string $customerName): string
    {
        $netAmount = $items[0]['NETAMOUNT'] ?? 0;
        $pdf = Pdf::loadView('pdf.external_bill', compact('items', 'billNo', 'billDate', 'customerName', 'netAmount'))
            ->setPaper('a4', 'portrait');

        $safeBillNo = str_replace(['/', '\\'], '_', $billNo);
        $tmpPath = sys_get_temp_dir() . "/bill_{$safeBillNo}_" . time() . '.pdf';
        $pdf->save($tmpPath);
        
        $r2Path = $this->getCachedFilePath('pdf', $billNo);
        Storage::disk('r2')->putFileAs('bills/pdf', new \Illuminate\Http\File($tmpPath), "bill_{$safeBillNo}.pdf");
        @unlink($tmpPath);
        
        return $r2Path;
    }
}

