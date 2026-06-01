<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leo Pharma - Tax Invoice</title>

<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9px; background: #fff; color: #000; }

  .page {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 4mm;
    background: #fff;
    border: 1px solid #000;
  }

  /* ── HEADER ── */
  .header {
    display: grid;
    grid-template-columns: 38mm 1fr 55mm 70mm;
    border: 1px solid #000;
    border-bottom: none;
  }

  .logo-cell {
    border-right: 1px solid #000;
    padding: 3mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    float: left;
    width: 35mm;
  }
  .logo-circle {
    width: 22mm; height: 22mm;
    border: 2px solid #2e7d32;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 900; color: #2e7d32; letter-spacing: -1px;
    margin: 0 auto;
    text-align: center;
    line-height: 22mm;
  }
  .logo-group { font-size: 7px; color: #555; margin-top: 2px; text-align: center; }
  .logo-since { font-size: 7px; color: #555; margin-top: 2px; text-align: center; }

  .company-cell {
    border-right: 1px solid #000;
    padding: 2mm 3mm;
    line-height: 1.5;
    float: left;
    width: 75mm;
  }
  .company-name { font-size: 13px; font-weight: 900; letter-spacing: 0.5px; }
  .company-sub { font-size: 8px; }
  .company-detail { font-size: 7.5px; }

  .invoice-title-cell {
    border-right: 1px solid #000;
    padding: 2mm;
    text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
    gap: 2mm;
    float: left;
    width: 45mm;
  }
  .tax-invoice-title {
    font-size: 14px; font-weight: 900; border-bottom: 1px solid #000;
    width: 100%; text-align: center; padding-bottom: 1mm;
  }
  .credit-label {
    font-size: 11px; font-weight: 700; border: 1px solid #000;
    padding: 1px 8px; margin-top: 1mm;
    display: inline-block;
  }
  .inv-detail-row { display: flex; justify-content: space-between; width: 100%; font-size: 8px; clear: both;}
  .inv-detail-row span:first-child { font-weight: 600; float: left; }
  .inv-detail-row span:last-child { font-weight: 700; float: right; }
  .salesman-row { width: 100%; font-size: 7.5px; display: flex; justify-content: space-between; clear: both;}
  .salesman-row span:first-child { float: left; }
  .salesman-row span:last-child { float: right; }
  .order-row { width: 100%; font-size: 7.5px; display: flex; justify-content: space-between; clear: both;}
  .order-row span:first-child { float: left; }
  .order-row span:last-child { float: right; }

  .customer-cell {
    padding: 2mm;
    position: relative;
    float: right;
    width: 46mm;
  }
  .copy-badge {
    position: absolute; top: 2mm; right: 2mm;
    border: 1.5px solid #000; padding: 1px 6px;
    font-size: 9px; font-weight: 700;
  }
  .customer-label { font-size: 7.5px; text-decoration: underline; font-style: italic; margin-bottom: 1mm; }
  .customer-name { font-size: 10px; font-weight: 900; }
  .customer-addr { font-size: 8px; line-height: 1.4; }
  .customer-gst-row { font-size: 7.5px; display: flex; justify-content: space-between; margin-top: 1mm; clear: both; }
  .customer-gst-row span:first-child { float: left; }
  .customer-gst-row span:last-child { float: right; }

  /* ── E-WAY / IRN ROW ── */
  .eway-row {
    border: 1px solid #000;
    border-bottom: none;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    font-size: 8px;
    clear: both;
    width: 100%;
  }
  .eway-cell { padding: 1mm 2mm; border-right: 1px solid #000; float: left; width: 33.33%; box-sizing: border-box; }
  .eway-cell:last-child { border-right: none; }
  .eway-cell span { font-weight: 600; }

  /* ── TABLE ── */
  .items-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #000;
    border-top: none;
    clear: both;
  }
  .items-table th, .items-table td {
    border: 1px solid #000;
    padding: 1px 2px;
    text-align: center;
    font-size: 7.5px;
    vertical-align: middle;
  }
  .items-table th {
    background: #f0f0f0;
    font-weight: 700;
    font-size: 7px;
    line-height: 1.3;
  }
  .items-table td.left { text-align: left; }
  .items-table tr:nth-child(even) td { background: #fafafa; }

  /* spacer rows */
  .items-table tr.empty-row td { height: 6mm; border: none; border-left: 1px solid #000; border-right: 1px solid #000; }
  .items-table tr.last-empty-row td { border-bottom: 1px solid #000; }

  /* ── FOOTER AREA ── */
  .footer-area {
    display: grid;
    grid-template-columns: 1fr 180px;
    border: 1px solid #000;
    border-top: none;
    width: 100%;
    clear: both;
  }

  .footer-left { border-right: 1px solid #000; float: left; width: 145mm; box-sizing: border-box; }

  .message-row {
    padding: 1mm 2mm;
    font-size: 8px;
    font-weight: 700;
    border-bottom: 1px solid #000;
  }

  .bank-gst-row {
    display: grid;
    grid-template-columns: 38mm 1fr;
    border-bottom: 1px solid #000;
    clear: both;
    width: 100%;
  }

  .bank-section {
    border-right: 1px solid #000;
    padding: 1mm 2mm;
    float: left;
    width: 38mm;
    box-sizing: border-box;
  }
  .bank-title { font-size: 8px; font-weight: 700; text-decoration: underline; margin-bottom: 1mm; }
  .bank-detail { font-size: 7.5px; line-height: 1.5; }
  .bank-qr-wrap { margin-top: 2mm; }

  .gst-section {
    float: right;
    width: 106mm;
    box-sizing: border-box;
    padding: 1mm;
  }

  .gst-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5px;
  }
  .gst-table th, .gst-table td {
    border: 1px solid #000;
    padding: 1px 3px;
    text-align: right;
    font-size: 7px;
  }
  .gst-table th { background: #f0f0f0; font-weight: 700; text-align: center; }
  .gst-table td:first-child { text-align: center; }

  .pl-route-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-top: 1px solid #000;
    font-size: 8px;
    margin-top: 2mm;
    clear: both;
  }
  .pl-cell { padding: 1mm 2mm; border-right: 1px solid #000; float: left; width: 50mm; }
  .pl-row { display: flex; gap: 3mm; margin-bottom: 1px; clear: both;}
  .pl-row span:first-child { float: left; }
  .pl-row span:last-child { float: right; }
  .pl-label { font-weight: 700; min-width: 14mm; }

  .cust-order-ref {
    float: right; width: 50mm; padding: 1mm; font-size: 8px; font-weight: 700;
  }

  .prepared-section {
    padding: 1mm 2mm;
    font-size: 7.5px;
    border-top: 1px solid #000;
    clear: both;
  }
  .prep-row { display: flex; gap: 3mm; margin-bottom: 1px; clear: both; width: 50%; float: left; }
  .prep-row span:first-child { float: left; }
  .prep-row span:last-child { float: right; padding-right: 5mm; }
  .prep-label { font-weight: 700; min-width: 18mm; }

  .dynamic-qr-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 2mm;
    border-top: 1px solid #000;
  }

  .rs-in-words-row {
    padding: 1mm 2mm;
    font-size: 7.5px;
    border-top: 1px solid #000;
    clear: both;
  }
  .rs-label { font-weight: 700; }

  .declaration-row {
    padding: 1mm 2mm;
    font-size: 6.8px;
    line-height: 1.4;
    border-top: 1px solid #000;
  }

  .powered-row {
    display: flex; justify-content: space-between; align-items: center;
    border-top: 1px solid #000;
    padding: 1mm 2mm;
    font-size: 7px;
  }

  /* ── FOOTER RIGHT (summary) ── */
  .footer-right { padding: 1mm 2mm; float: right; width: 55mm; box-sizing: border-box; }
  .summary-table { width: 100%; font-size: 8px; }
  .summary-table tr td { padding: 0.5px 1mm; }
  .summary-table tr td:last-child { text-align: right; font-weight: 600; }
  .bill-amount-row td { font-size: 11px; font-weight: 900; border-top: 1.5px solid #000; padding-top: 2px; }

  .for-company {
    font-size: 8px; font-weight: 700; text-align: right;
    margin-top: 8mm;
    line-height: 1.4;
  }

  @media print {
    body { margin: 0; }
    .page { border: none; margin: 0; width: 100%; }
  }
  
  .clear { clear: both; }
</style>
</head>
<body>

@php
$grossAmount = 0;
$discountAmount = 0;
$taxableAmount = 0;
$cgstAmount = 0;
$sgstAmount = 0;
$igstAmount = 0;
$tcsAmount = 0;
$debitNote = 0;
$qtyTotal = 0;
$itemTotal = count($items);

$gstBreakup = [];

foreach ($items as $item) {
    $qty = (int)($item['QUANTITY'] ?? 0) + (int)($item['FREE'] ?? 0);
    $qtyTotal += $qty;
    
    $mrp = (float)($item['PMRP'] ?? 0);
    $rate = (float)($item['SRATE'] ?? 0);
    
    $gross = $rate * $qty;
    $grossAmount += $gross;
    
    $discVal = (float)($item['DISVALUE'] ?? 0);
    $discountAmount += $discVal;
    
    $taxable = (float)($item['TAXABLE'] ?? 0);
    $taxableAmount += $taxable;
    
    $gstVal = (float)($item['GSTVALUE'] ?? 0);
    $gstRate = (float)($item['GSTRATE'] ?? 0);
    
    $cgstAmount += $gstVal / 2;
    $sgstAmount += $gstVal / 2;
    
    $rateKey = (string)$gstRate;
    if (!isset($gstBreakup[$rateKey])) {
        $gstBreakup[$rateKey] = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];
    }
    $gstBreakup[$rateKey]['taxable'] += $taxable;
    $gstBreakup[$rateKey]['cgst'] += $gstVal / 2;
    $gstBreakup[$rateKey]['sgst'] += $gstVal / 2;
    $gstBreakup[$rateKey]['total'] += $gstVal;
}

$computedTotal = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;
$billAmount = $netAmount;
$roundOff = $billAmount - $computedTotal;

$nf = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
$words = strtoupper($nf->format($netAmount)) . ' ONLY';

$custModel = \App\Models\Customer::whereHas('user', function($q) use($customerName) {
    $q->where('name', $customerName);
})->with('user')->first();

$custAddress = "";
$custPhone = $custModel ? $custModel->user->phone : "";
$custGst = $custModel ? $custModel->gstin : "";
@endphp

<div class="page">

  <!-- ═══════════════ HEADER ═══════════════ -->
  <div class="header">

    <!-- Logo -->
    <div class="logo-cell">
      <div class="logo-circle">LEO</div>
      <div class="logo-group">GROUP</div>
      <div class="logo-since">Since 1974</div>
      <div style="font-size:6.5px;color:#555;margin-top:1mm;">www.leogroup.in</div>
    </div>

    <!-- Company Details -->
    <div class="company-cell">
      <div class="company-name">LEO PHARMA DISTRIBUTORS P.LTD</div>
      <div class="company-sub">DOOR NO.17/18/C, LEO LOGISTICS HUB</div>
      <div class="company-sub">ELAMTHURUTHY, KALADY, KUTTANELLUR P.O</div>
      <div class="company-sub">THRISSUR - 680 014</div>
      <div class="company-detail" style="margin-top:1mm;">PHONE : 0487 2224080, 3506700</div>
      <div class="company-detail">e-mail : order.leogroup@gmail.com</div>
      <div class="company-detail">GST IN : &nbsp;<strong>32AALCA0738P1ZG</strong> - Kerala(32)</div>
      <div class="company-detail" style="font-size:6.5px;">DL.NOS : WLF20B2023KL001698,WLF21B2023KL001683</div>
      <div class="company-detail" style="font-size:6.5px;">WLF20B2023KL001692,WLF21B2023KL001676</div>
    </div>

    <!-- Invoice Title + Details -->
    <div class="invoice-title-cell">
      <div class="tax-invoice-title">TAX INVOICE</div>
      <div class="credit-label">- CREDIT -</div>
      <div style="height:2mm;"></div>
      <div class="inv-detail-row"><span>Invoice No</span><span>{{ $billNo }}</span></div>
      <div class="inv-detail-row"><span>Invoice Date</span><span>{{ \Carbon\Carbon::parse($billDate)->format('d-M-Y') }}</span></div>
      <div style="height:1mm;"></div>
      <div class="salesman-row"><span>Salesman :</span><span>NA</span></div>
      <div class="order-row"><span>Order No.</span><span>NA</span></div>
    </div>

    <!-- Customer Info -->
    <div class="customer-cell">
      <div class="copy-badge">COPY</div>
      <div class="customer-label">Customer Name &amp; Address :</div>
      <div class="customer-name">{{ $customerName }}</div>
      <div class="customer-addr">{{ $custAddress }}</div>
      <div class="customer-addr">Phone : &nbsp;{{ $custPhone }}</div>
      <div class="customer-gst-row">
        <span>GST IN &nbsp; {{ $custGst }}</span>
        <span>Kerala</span>
      </div>
    </div>
    <div class="clear"></div>
  </div>

  <!-- ═══════════════ E-WAY / VEHICLE / IRN ═══════════════ -->
  <div class="eway-row">
    <div class="eway-cell"><span>E-way No :</span> &nbsp; NA</div>
    <div class="eway-cell"><span>Vehicle No</span> &nbsp; NA</div>
    <div class="eway-cell"><span>IRN :</span> &nbsp; NA</div>
    <div class="clear"></div>
  </div>

  <!-- ═══════════════ ITEMS TABLE ═══════════════ -->
  <table class="items-table">
    <thead>
      <tr>
        <th>M.R.P</th>
        <th>BATCH NO</th>
        <th>EXP<br>DATE</th>
        <th>MFR/<br>MKT</th>
        <th style="text-align:left;">ITEM NAME &amp; PACKING</th>
        <th>HSN<br>CODE</th>
        <th>QTY</th>
        <th>FREE</th>
        <th>RATE</th>
        <th>AMOUNT</th>
        <th>SCH<br>DIS%</th>
        <th>CASH<br>DIS%</th>
        <th>DISC<br>AMOUNT</th>
        <th>TAXABLE<br>AMOUNT</th>
        <th>GST<br>%</th>
        <th>GST<br>VALUE</th>
        <th>NET<br>AMOUNT</th>
      </tr>
    </thead>
    <tbody id="items-tbody">
      @foreach($items as $item)
      <tr>
        <td>{{ number_format((float)($item['PMRP'] ?? 0), 2) }}</td>
        <td>{{ $item['BATCHNO'] ?? '' }}</td>
        <td>{{ $item['EXPIRYDATE'] ?? '' }}</td>
        <td>{{ substr($item['COMPNAME'] ?? '', 0, 8) }}</td>
        <td class="left">{{ $item['ITEMNAME'] ?? '' }}</td>
        <td>{{ $item['HSNCODE'] ?? '' }}</td>
        <td>{{ $item['QUANTITY'] ?? 0 }}</td>
        <td>{{ $item['FREE'] ?? 0 }}</td>
        <td>{{ number_format((float)($item['SRATE'] ?? 0), 2) }}</td>
        <td>{{ number_format(((float)($item['SRATE'] ?? 0)) * ((int)($item['QUANTITY'] ?? 0)), 2) }}</td>
        <td>{{ $item['DISCOUNT'] ?? 0 }}</td>
        <td>{{ $item['CASHDISPER'] ?? 0 }}</td>
        <td>{{ number_format((float)($item['DISVALUE'] ?? 0), 2) }}</td>
        <td>{{ number_format((float)($item['TAXABLE'] ?? 0), 2) }}</td>
        <td>{{ $item['GSTRATE'] ?? 0 }}</td>
        <td>{{ number_format((float)($item['GSTVALUE'] ?? 0), 2) }}</td>
        <td>{{ number_format((float)($item['TOTALAMOUNT'] ?? 0), 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <!-- Empty filler rows -->
    @for($i=0; $i<max(0, 15 - count($items)); $i++)
    <tr class="empty-row @if($i == max(0, 15 - count($items)) - 1) last-empty-row @endif"><td colspan="17"></td></tr>
    @endfor
  </table>

  <!-- ═══════════════ FOOTER ═══════════════ -->
  <div class="footer-area">

    <!-- LEFT SIDE -->
    <div class="footer-left">

      <!-- Message -->
      <div class="message-row">Message : </div>

      <!-- Bank + GST Table -->
      <div class="bank-gst-row">

        <!-- Bank Details -->
        <div class="bank-section">
          <div class="bank-title">Bank Details</div>
          <div class="bank-detail">Bank : </div>
          <div class="bank-detail">Br : </div>
          <div class="bank-detail">A.c No :</div>
          <div class="bank-detail"></div>
          <div class="bank-detail">IFSC : </div>
          <div class="bank-qr-wrap">
          </div>
        </div>

        <!-- GST Breakup Table -->
        <div class="gst-section">
          <table class="gst-table">
            <thead>
              <tr>
                <th>GST%</th>
                <th>TAXABLE</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                <th>TOTALGST</th>
              </tr>
            </thead>
            <tbody id="gst-tbody">
              @foreach($gstBreakup as $rate => $g)
              <tr>
                <td>{{ $rate }}</td>
                <td>{{ number_format($g['taxable'], 2) }}</td>
                <td>{{ number_format($g['cgst'], 2) }}</td>
                <td>{{ number_format($g['sgst'], 2) }}</td>
                <td>{{ number_format($g['igst'], 2) }}</td>
                <td>{{ number_format($g['total'], 2) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>

          <!-- PL / Route -->
          <div class="pl-route-section">
            <div class="pl-cell">
              <div class="pl-row"><span class="pl-label">PL.NO :</span> <span></span></div>
              <div class="pl-row"><span class="pl-label">Route :</span> <span></span></div>
            </div>
            <div class="cust-order-ref">Customer Order Ref. <br><span style="font-weight:400;"></span></div>
            <div class="clear"></div>
          </div>

          <!-- Prepared / Packed / Item Total -->
          <div class="prepared-section">
            <div class="prep-row"><span class="prep-label">Prepared by :</span><span>System</span></div>
            <div class="prep-row"><span class="prep-label">Packed by :</span><span></span></div>
            <div class="prep-row"><span class="prep-label">Item Total</span><span>{{ $itemTotal }}</span></div>
            <div class="prep-row"><span class="prep-label">Qty Total</span><span>{{ $qtyTotal }}</span></div>
            <div class="prep-row"><span class="prep-label">Bill time</span><span>{{ now()->format('h:i A') }}</span></div>
            <div class="clear"></div>
          </div>

        </div>
        <div class="clear"></div>
      </div>

      <!-- RS in words -->
      <div class="rs-in-words-row">
        <span class="rs-label">RS in words :</span> &nbsp; {{ $words }}
      </div>

      <!-- Declaration -->
      <div class="declaration-row">
        <strong>Declaration</strong> : We hereby warrenty that t he medicine purchased under this memo do not contravene in any way the provision of Section 18 of the Drug &amp; Cosmetics Act 1940.<br>
        (1) Goods once sold will not be taken back.(2) Subject to Thrissur jurisdiction only.(3) Interest @18% on overdue payments.(4) Please check Batchno &amp; MRP on NPPA products on delivery.(5) We do not take any responsibility for losses of goods.(6) Payments made in cash should always be against duly signed receipts
      </div>

      <!-- Powered -->
      <div class="powered-row">
        <span>Powered by : Green Software Solutions, Ph : +91 9446760469</span>
        <strong>E &amp; OE</strong>
      </div>
    </div>

    <!-- RIGHT SIDE: Summary -->
    <div class="footer-right">
      <table class="summary-table">
        <tr><td>Gross Amount</td><td>{{ number_format($grossAmount, 2) }}</td></tr>
        <tr><td>Discount Amount</td><td>{{ number_format($discountAmount, 2) }}</td></tr>
        <tr><td>Taxable Amount</td><td>{{ number_format($taxableAmount, 2) }}</td></tr>
        <tr><td>CGST Amount</td><td>{{ number_format($cgstAmount, 2) }}</td></tr>
        <tr><td>SGST Amount</td><td>{{ number_format($sgstAmount, 2) }}</td></tr>
        <tr><td>IGST Amount</td><td>{{ number_format($igstAmount, 2) }}</td></tr>
        <tr><td>TCS @ 0.1</td><td>{{ number_format($tcsAmount, 2) }}</td></tr>
        <tr><td>Debit Note</td><td>{{ number_format($debitNote, 2) }}</td></tr>
        <tr><td>Round off</td><td>{{ number_format($roundOff, 2) }}</td></tr>
        <tr class="bill-amount-row">
          <td>Bill Amount &nbsp; ₹</td>
          <td>{{ number_format($billAmount, 2) }}</td>
        </tr>
      </table>
      <div class="for-company">For LEO PHARMA<br>DISTRIBUTORS P.LTD</div>
      <div style="font-size:7px;text-align:right;margin-top:12mm;border-top:1px solid #000;padding-top:1mm;">Page 1 of 1</div>
    </div>
    
    <div class="clear"></div>
  </div>

</div>

</body>
</html>
