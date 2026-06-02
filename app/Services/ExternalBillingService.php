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
                ->withHeaders([
                    'ngrok-skip-browser-warning' => 'true'
                ])
                ->get("{$this->baseUrl}/API/announcements/customer_details.php");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data ?? [];
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
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->asMultipart()
                ->post("{$this->baseUrl}/API/announcements/bill_master.php", [
                    ['name' => 'cucode', 'contents' => $cucode],
                    ['name' => 'from_date', 'contents' => $fromDate],
                    ['name' => 'to_date', 'contents' => $toDate]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data ?? [];
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
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->asMultipart()
                ->post("{$this->baseUrl}/API/announcements/bill_details.php", [
                    ['name' => 'billno', 'contents' => (string)$numericId]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data ?? [];
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

        // Company header
        $compName = $items[0]['COMPNAME'] ?? 'LEO PHARMA';
        $sheet->setCellValue('A1', 'LEO GROUP — BILL STATEMENT');
        $sheet->mergeCells('A1:T1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        ]);

        $sheet->setCellValue('A2', "Bill No: {$billNo}");
        $sheet->setCellValue('K2', "Date: {$billDate}");
        $sheet->mergeCells('A2:J2');
        $sheet->mergeCells('K2:T2');

        // Column headers
        $headers = [
            'A' => 'Item Name', 'B' => 'Company', 'C' => 'Item Code',
            'D' => 'Packing', 'E' => 'Batch No', 'F' => 'Expiry',
            'G' => 'Qty', 'H' => 'Free', 'I' => 'Rate (₹)', 'J' => 'MRP (₹)',
            'K' => 'Disc %', 'L' => 'Disc Val', 'M' => 'Cash Disc',
            'N' => 'Taxable', 'O' => 'GST %', 'P' => 'GST Val',
            'Q' => 'Total (₹)', 'R' => 'HSN Code',
        ];

        $row = 4;
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:R{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $row = 5;
        foreach ($items as $item) {
            $sheet->setCellValue("A{$row}", $item['ITEMNAME'] ?? '');
            $sheet->setCellValue("B{$row}", $item['COMPNAME'] ?? '');
            $sheet->setCellValue("C{$row}", $item['ITEMCODE'] ?? '');
            $sheet->setCellValue("D{$row}", $item['PACKING'] ?? '');
            $sheet->setCellValue("E{$row}", $item['BATCHNO'] ?? '');
            $sheet->setCellValue("F{$row}", $item['EXPIRYDATE'] ?? '');
            $sheet->setCellValue("G{$row}", $item['QUANTITY'] ?? 0);
            $sheet->setCellValue("H{$row}", $item['FREE'] ?? 0);
            $sheet->setCellValue("I{$row}", $item['SRATE'] ?? 0);
            $sheet->setCellValue("J{$row}", $item['PMRP'] ?? 0);
            $sheet->setCellValue("K{$row}", $item['DISCOUNT'] ?? 0);
            $sheet->setCellValue("L{$row}", $item['DISVALUE'] ?? 0);
            $sheet->setCellValue("M{$row}", $item['CASHDISPER'] ?? 0);
            $sheet->setCellValue("N{$row}", $item['TAXABLE'] ?? 0);
            $sheet->setCellValue("O{$row}", $item['GSTRATE'] ?? 0);
            $sheet->setCellValue("P{$row}", $item['GSTVALUE'] ?? 0);
            $sheet->setCellValue("Q{$row}", $item['TOTALAMOUNT'] ?? 0);
            $sheet->setCellValue("R{$row}", $item['HSNCODE'] ?? '');
            $row++;
        }

        // Total row
        $netAmount = $items[0]['NETAMOUNT'] ?? 0;
        $sheet->setCellValue("N{$row}", 'NET AMOUNT:');
        $sheet->setCellValue("Q{$row}", $netAmount);
        $sheet->getStyle("N{$row}:Q{$row}")->getFont()->setBold(true);

        // Auto-size columns
        foreach (range('A', 'R') as $col) {
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
        ], "\t");

        $netAmount = $items[0]['NETAMOUNT'] ?? 0;

        foreach ($items as $item) {
            $qty = $item['QUANTITY'] ?? 0;
            $ptr = $item['SRATE'] ?? 0;
            $amount = round($qty * $ptr, 2);

            fputcsv($fp, [
                $billNo,
                $billDate,
                $item['COMPNAME'] ?? '',
                $item['ITEMCODE'] ?? '',
                $item['ITEMNAME'] ?? '',
                $item['PACKING'] ?? '',
                $item['BATCHNO'] ?? '',
                $item['EXPIRYDATE'] ?? '',
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
            ], "\t");
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

