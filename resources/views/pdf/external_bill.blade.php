<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Leo Pharma - Tax Invoice V3</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 8px;
      background: #fff;
      color: #000;
    }

    .page {
      width: 100%;
      margin: 0 auto;
      background: #fff;
      border: 4px double #2c3e50;
      padding: 4px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    td,
    th {
      padding: 2px;
      vertical-align: top;
    }

    /* Header Table */
    .header-table {
      border: 1px solid #000;
      border-bottom: none;
    }

    .logo-cell {
      text-align: center;
      width: 15%;
      border-right: 1px solid #000;
    }

    .logo-circle {
      width: 50px;
      height: 50px;
      border: 2px solid #2e7d32;
      border-radius: 50%;
      margin: 0 auto;
      font-size: 10px;
      font-weight: bold;
      color: #2e7d32;
      line-height: 50px;
    }

    .company-cell {
      width: 40%;
      border-right: 1px solid #000;
      font-size: 7.5px;
      line-height: 1.2;
    }

    .company-name {
      font-size: 11px;
      font-weight: bold;
      margin-bottom: 2px;
    }

    .inv-title-cell {
      width: 22%;
      border-right: 1px solid #000;
      text-align: center;
      font-size: 7.5px;
    }

    .cust-cell {
      width: 23%;
      font-size: 7.5px;
      position: relative;
    }

    /* E-Way Row */
    .eway-table {
      border: 1px solid #000;
      border-bottom: none;
      font-size: 7.5px;
    }

    .eway-table td {
      width: 33.3%;
      border-right: 1px solid #000;
      font-weight: bold;
    }

    .eway-table td:last-child {
      border-right: none;
    }

    /* Items Table */
    .items-table {
      border: 1px solid #000;
      border-top: none;
    }

    .items-table th {
      border: 1px solid #000;
      font-size: 6.5px;
      background: #eef2f5;
      font-weight: bold;
      text-align: center;
      padding: 3px 1px;
    }

    .items-table td {
      border-left: 1px solid #000;
      border-right: 1px solid #000;
      border-bottom: 1px dotted #aaa;
      text-align: center;
      font-size: 7px;
      padding: 1px;
    }

    .items-table td.left {
      text-align: left;
    }

    .items-table tr.empty-row td {
      height: 10px;
    }

    /* Smaller filler height */

    /* Footer Section */
    .footer-table {
      border: 1px solid #000;
      border-top: 1px solid #000;
    }

    .footer-left {
      width: 70%;
      border-right: 1px solid #000;
      padding: 0;
    }

    .footer-right {
      width: 30%;
      padding: 2px;
    }

    .summary-table td {
      border: none;
      font-size: 7.5px;
      padding: 1px;
    }

    .summary-table td:last-child {
      text-align: right;
      font-weight: bold;
    }

    .bill-amt-row {
      border-top: 1px solid #000;
      font-size: 10px !important;
      font-weight: bold;
    }

    .bank-gst-table td {
      padding: 2px;
      font-size: 7.5px;
      vertical-align: top;
    }

    .bank-cell {
      width: 30%;
      border-right: 1px solid #000;
      border-bottom: 1px solid #000;
    }

    .gst-cell {
      width: 70%;
      border-bottom: 1px solid #000;
      padding: 0 !important;
    }

    .inner-gst-table th,
    .inner-gst-table td {
      border: 1px solid #000;
      font-size: 6.5px;
      text-align: right;
      padding: 1px;
    }

    .inner-gst-table th {
      text-align: center;
      font-weight: bold;
      background: #eef2f5;
    }

    .prep-table td {
      border-bottom: 1px solid #000;
      font-size: 7px;
    }
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
      $qty = (int) ($item['QUANTITY'] ?? 0) + (int) ($item['FREE'] ?? 0);
      $qtyTotal += $qty;
      $rate = (float) ($item['SRATE'] ?? 0);
      $grossAmount += ($rate * $qty);
      $discountAmount += (float) ($item['DISVALUE'] ?? 0);
      $taxable = (float) ($item['TAXABLE'] ?? 0);
      $taxableAmount += $taxable;
      $gstVal = (float) ($item['GSTVALUE'] ?? 0);
      $cgstAmount += $gstVal / 2;
      $sgstAmount += $gstVal / 2;
      $gstRate = (string) ((float) ($item['GSTRATE'] ?? 0));
      if (!isset($gstBreakup[$gstRate]))
        $gstBreakup[$gstRate] = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total' => 0];
      $gstBreakup[$gstRate]['taxable'] += $taxable;
      $gstBreakup[$gstRate]['cgst'] += $gstVal / 2;
      $gstBreakup[$gstRate]['sgst'] += $gstVal / 2;
      $gstBreakup[$gstRate]['total'] += $gstVal;
    }
    $computedTotal = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;
    $billAmount = $netAmount;
    $roundOff = $billAmount - $computedTotal;

    if (!function_exists('numToWords')) {
      function numToWords($num)
      {
        $ones = array(
          0 => 'ZERO',
          1 => 'ONE',
          2 => 'TWO',
          3 => 'THREE',
          4 => 'FOUR',
          5 => 'FIVE',
          6 => 'SIX',
          7 => 'SEVEN',
          8 => 'EIGHT',
          9 => 'NINE',
          10 => 'TEN',
          11 => 'ELEVEN',
          12 => 'TWELVE',
          13 => 'THIRTEEN',
          14 => 'FOURTEEN',
          15 => 'FIFTEEN',
          16 => 'SIXTEEN',
          17 => 'SEVENTEEN',
          18 => 'EIGHTEEN',
          19 => 'NINETEEN'
        );
        $tens = array(
          0 => 'ZERO',
          1 => 'TEN',
          2 => 'TWENTY',
          3 => 'THIRTY',
          4 => 'FORTY',
          5 => 'FIFTY',
          6 => 'SIXTY',
          7 => 'SEVENTY',
          8 => 'EIGHTY',
          9 => 'NINETY'
        );
        $hundreds = array('HUNDRED', 'THOUSAND', 'MILLION', 'BILLION', 'TRILLION', 'QUADRILLION');
        $num = number_format($num, 2, '.', '');
        $num_arr = explode('.', $num);
        $whole = $num_arr[0];
        $dec = $num_arr[1];
        $whole_arr = array_reverse(explode(',', number_format($whole)));
        $k = 0;
        $words = '';
        foreach ($whole_arr as $val) {
          $val = (int) $val;
          if ($val == 0) {
            $k++;
            continue;
          }
          $w = '';
          if ($val < 20) {
            $w = $ones[$val];
          } elseif ($val < 100) {
            $w = $tens[(int) ($val / 10)] . ($val % 10 != 0 ? ' ' . $ones[$val % 10] : '');
          } else {
            $w = $ones[(int) ($val / 100)] . ' ' . $hundreds[0] . ($val % 100 != 0 ? ' AND ' . numToWords($val % 100) : '');
          }
          $words = $w . ($k > 0 ? ' ' . $hundreds[$k] : '') . ' ' . $words;
          $k++;
        }
        return trim($words);
      }
    }
    $words = numToWords($netAmount) . ' ONLY';

    $custModel = \App\Models\Customer::whereHas('user', function ($q) use ($customerName) {
      $q->where('name', $customerName);
    })->with('user')->first();
    $custPhone = $custModel ? $custModel->user->phone : "";
    $custGst = $custModel ? $custModel->gstin : "";
  @endphp

  <div class="page">
    <table class="header-table">
      <tr>
        <td class="logo-cell">
          <img src="{{ public_path('leo_logo.jpg') }}"
            style="width: 60px; height: auto; margin: 0 auto; display: block;" alt="LEO">
          <div>GROUP</div>
          <div>Since 1974</div>
        </td>
        <td class="company-cell">
          <div class="company-name">LEO PHARMA DISTRIBUTORS P.LTD</div>
          DOOR NO.17/18/C, LEO LOGISTICS HUB<br>ELAMTHURUTHY, KALADY, KUTTANELLUR P.O<br>THRISSUR - 680 014<br>
          PHONE : 0487 2224080, 3506700 | e-mail : order.leogroup@gmail.com<br>
          <strong>GST IN : 32AALCA0738P1ZG</strong> - Kerala(32)
        </td>
        <td class="inv-title-cell">
          <strong style="font-size:10px; border-bottom:1px solid #000; display:block; padding-bottom:1px;">TAX
            INVOICE</strong>
          <div style="margin:2px 0;"><strong>- CREDIT -</strong></div>
          <table style="width:100%; text-align:left; margin-top:2px;">
            <tr>
              <td>Invoice No</td>
              <td style="text-align:right; font-weight:bold;">{{ $billNo }}</td>
            </tr>
            <tr>
              <td>Date</td>
              <td style="text-align:right; font-weight:bold;">{{ \Carbon\Carbon::parse($billDate)->format('d-M-Y') }}
              </td>
            </tr>
            <tr>
              <td>Salesman</td>
              <td style="text-align:right;">NA</td>
            </tr>
          </table>
        </td>
        <td class="cust-cell">
          <div style="text-decoration:underline; font-style:italic;">Customer Name &amp; Address :</div>
          <strong style="font-size:9px;">{{ $customerName }}</strong><br>
          Phone: {{ $custPhone }}<br>
          GST IN : {{ $custGst }} | Kerala
        </td>
      </tr>
    </table>

    <table class="eway-table">
      <tr>
        <td>E-way No : NA</td>
        <td>Vehicle No : NA</td>
        <td>IRN : NA</td>
      </tr>
    </table>

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
      <tbody>
        @foreach($items as $item)
          <tr>
            <td>{{ number_format((float) ($item['PMRP'] ?? 0), 2) }}</td>
            <td>{{ $item['BATCHNO'] ?? '' }}</td>
            <td>{{ $item['EXPIRYDATE'] ?? '' }}</td>
            <td>{{ substr($item['COMPNAME'] ?? '', 0, 8) }}</td>
            <td class="left">{{ $item['ITEMNAME'] ?? '' }}</td>
            <td>{{ $item['HSNCODE'] ?? '' }}</td>
            <td>{{ $item['QUANTITY'] ?? 0 }}</td>
            <td>{{ $item['FREE'] ?? 0 }}</td>
            <td>{{ number_format((float) ($item['SRATE'] ?? 0), 2) }}</td>
            <td>{{ number_format(((float) ($item['SRATE'] ?? 0)) * ((int) ($item['QUANTITY'] ?? 0)), 2) }}</td>
            <td>{{ $item['DISCOUNT'] ?? 0 }}</td>
            <td>{{ $item['CASHDISPER'] ?? 0 }}</td>
            <td>{{ number_format((float) ($item['DISVALUE'] ?? 0), 2) }}</td>
            <td>{{ number_format((float) ($item['TAXABLE'] ?? 0), 2) }}</td>
            <td>{{ $item['GSTRATE'] ?? 0 }}</td>
            <td>{{ number_format((float) ($item['GSTVALUE'] ?? 0), 2) }}</td>
            <td>{{ number_format((float) ($item['TOTALAMOUNT'] ?? 0), 2) }}</td>
          </tr>
        @endforeach
        @for($i = 0; $i < max(0, 15 - count($items)); $i++)
          <tr class="empty-row">
            <td colspan="17"></td>
          </tr>
        @endfor
        <tr>
          <td colspan="17" style="border-top:1px solid #000; height:1px; padding:0;"></td>
        </tr>
      </tbody>
    </table>

    <table class="footer-table">
      <tr>
        <td class="footer-left">
          <div style="padding:2px; border-bottom:1px solid #000; font-size:7.5px;"><strong>Message :</strong></div>
          <table class="bank-gst-table">
            <tr>
              <td class="bank-cell">
                <table style="width:100%; border:none; padding:0; margin:0;">
                  <tr>
                    <td style="border:none; padding:0; width:60%;">
                      <strong><u>Bank Details</u></strong><br>
                      Bank : HDFC Bank<br>Br : Palace Road, Tcr<br>A.c No : 50200043969997<br>IFSC : HDFC0000057
                    </td>
                    <td style="border:none; padding:0; width:40%; text-align:right;">
                      <img src="https://chart.googleapis.com/chart?chs=60x60&cht=qr&chl=upi://pay?pa=leo@hdfcbank"
                        style="width:40px; height:40px;" alt="QR">
                    </td>
                  </tr>
                </table>
              </td>
              <td class="gst-cell">
                <table class="inner-gst-table">
                  <tr>
                    <th>GST%</th>
                    <th>TAXABLE</th>
                    <th>CGST</th>
                    <th>SGST</th>
                    <th>IGST</th>
                    <th>TOTALGST</th>
                  </tr>
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
                </table>
                <table class="prep-table" style="margin-top:1px;">
                  <tr>
                    <td width="33%"><strong>PL.NO :</strong></td>
                    <td width="33%"><strong>Route :</strong></td>
                    <td width="33%"><strong>Item Total:</strong> {{ $itemTotal }}</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
          <div style="padding:2px; border-bottom:1px solid #000; font-size:7.5px;">
            <strong>RS in words :</strong> {{ $words }}
          </div>
          <div style="padding:2px; font-size:6.5px;">
            <strong>Declaration :</strong> We hereby warranty that the medicine purchased under this memo do not
            contravene in any way the provision of Section 18 of the Drug &amp; Cosmetics Act 1940.
          </div>
        </td>
        <td class="footer-right">
          <table class="summary-table">
            <tr>
              <td>Gross Amount</td>
              <td>{{ number_format($grossAmount, 2) }}</td>
            </tr>
            <tr>
              <td>Discount Amount</td>
              <td>{{ number_format($discountAmount, 2) }}</td>
            </tr>
            <tr>
              <td>Taxable Amount</td>
              <td>{{ number_format($taxableAmount, 2) }}</td>
            </tr>
            <tr>
              <td>CGST Amount</td>
              <td>{{ number_format($cgstAmount, 2) }}</td>
            </tr>
            <tr>
              <td>SGST Amount</td>
              <td>{{ number_format($sgstAmount, 2) }}</td>
            </tr>
            <tr>
              <td>IGST Amount</td>
              <td>{{ number_format($igstAmount, 2) }}</td>
            </tr>
            <tr>
              <td>TCS @ 0.1</td>
              <td>{{ number_format($tcsAmount, 2) }}</td>
            </tr>
            <tr>
              <td>Debit Note</td>
              <td>{{ number_format($debitNote, 2) }}</td>
            </tr>
            <tr>
              <td>Round off</td>
              <td>{{ number_format($roundOff, 2) }}</td>
            </tr>
            <tr class="bill-amt-row">
              <td>Bill Amount &nbsp; Rs</td>
              <td>{{ number_format($billAmount, 2) }}</td>
            </tr>
          </table>
          <div style="text-align:right; font-size:7px; font-weight:bold; margin-top:10px;">For LEO
            PHARMA<br>DISTRIBUTORS P.LTD</div>
        </td>
      </tr>
    </table>
  </div>
</body>

</html>