<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leo Pharma Distributors – Tax Invoice</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; background: #fff; color: #000; }
  @page { margin: 15px; size: A4 landscape; }
  .page { width: 100%; position: relative; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
  
  /* Header Styles */
  .cname { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
  .caddr { font-size: 9px; line-height: 1.25; margin-bottom: 2px; }
  .cgst { font-size: 9px; font-weight: bold; margin-top: 2px; }
  .dl { font-size: 7.5px; margin-top: 1px; }

  .inv-title { font-size: 16px; font-weight: bold; text-align: center; padding: 4px 0; }
  .inv-sep { border-top: 1px solid #000; margin: 2px 0; }
  .inv-subtitle { font-size: 12px; font-weight: bold; text-align: center; padding: 4px 0; }
  
  .customer-label { font-size: 8.5px; font-style: italic; text-decoration: underline; margin-bottom: 2px; }
  .customer-name { font-size: 11px; font-weight: bold; margin-bottom: 2px; }
  .customer-addr { font-size: 8.5px; line-height: 1.25; }

  /* Eway Table */
  .eway-table { border: 1px solid #000; border-top: none; }
  .eway-table td { font-size: 9px; font-weight: bold; padding: 2px 5px; border-right: 1px solid #000; }
  .eway-table td:last-child { border-right: none; }

  /* Items Table */
  table.items-table { border: 1px solid #000; border-top: none; border-bottom: none; }
  table.items-table th { border: 0.5px solid #000; border-top: none; padding: 3px 2px; font-size: 8px; font-weight: bold; text-align: center; vertical-align: middle; }
  table.items-table td { border-left: 0.5px solid #000; border-right: 0.5px solid #000; padding: 2.5px 2px; font-size: 8.5px; text-align: center; height: 16px; }
  table.items-table td.left { text-align: left; }
  
  /* Footer */
  .footer-wrapper { border: 1px solid #000; border-top: 1px solid #000; }
  .msg-row { font-size: 11px; font-weight: bold; border-bottom: 1px solid #000; padding: 3px 5px; }
  
  .footer-blocks td { border-right: 1px solid #000; }
  
  /* Bank & GST */
  .bank-details { padding: 3px 5px; font-size: 9px; line-height: 1.3; }
  .bank-title { font-weight: bold; text-decoration: underline; margin-bottom: 2px; }
  
  table.gst-slab th { background: #999; color: #fff; font-size: 7.5px; padding: 2px; text-align: center; border: 1px solid #fff;}
  table.gst-slab td { font-size: 8px; text-align: right; padding: 2px; }
  
  .prep-table { width: 100%; border-top: 1px solid #000; margin-top: 3px; font-size: 8.5px;}
  .prep-table td { border: none !important; padding: 2px 4px; }
  
  /* Totals */
  table.totals-table td { padding: 2px 5px; font-size: 9px; border: none; }
  table.totals-table td:last-child { text-align: right; }
  .bill-amt-row td { font-size: 13px; font-weight: bold; border-top: 2px solid #000 !important; padding-top: 5px; padding-bottom: 5px; }
  
  /* Declaration & Bottom */
  .declaration { font-size: 7.5px; line-height: 1.25; padding: 3px 5px; border-top: 1px solid #000; }
  .rs-words { font-size: 9px; font-weight: bold; padding: 3px 5px; border-top: 1px solid #000; }
  .bottom-row { width: 100%; font-size: 8px; margin-top: 2px; font-weight: bold; }
  
  .ph { color: #1a56db; font-style: italic; }
</style>
</head>
<body>
<div class="page">
  
  <!-- Header -->
  <table style="border: 1px solid #000; border-bottom: none;">
    <tr>
      <!-- Left Column: Logo & Company -->
      <td style="width: 38%; border-right: 1px solid #000; padding: 4px;">
        <table style="border: none;">
          <tr>
            <td style="width: 25%; text-align: center; border: none;">
              <!-- Add logo if available, or just space -->
              <img src="https://raw.githubusercontent.com/s4rj/bms-assets/main/leo-logo-new.jpg" style="max-width: 80px;" alt="Logo" onerror="this.style.display='none'">
              <div style="font-size:8px; margin-top:2px; font-style:italic;">Since 1974</div>
            </td>
            <td style="width: 75%; border: none; padding-left: 5px;">
              <div class="cname">LEO PHARMA DISTRIBUTORS P.LTD</div>
              <div class="caddr">DOOR NO.17/18/C, LEO LOGISTICS HUB<br>ELAMTHURUTHY, KALADY, KUTTANELLUR P.O<br>THRISSUR – 680 014<br>PHONE : 0487 2224080, 3506700<br>e-mail : order.leogroup@gmail.com</div>
              <div class="cgst">GST IN :  32AALCA0738P1ZG - Kerala(32)</div>
              <div class="dl">DL.NOS : WLF20B2023KL001699, WLF21B2023KL001683<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WLF20B2023KL001692, WLF21B2023KL001676</div>
            </td>
          </tr>
        </table>
      </td>
      
      <!-- Middle Column: Invoice Details -->
      <td style="width: 32%; border-right: 1px solid #000;">
        <div class="inv-title">TAX INVOICE</div>
        <div class="inv-sep"></div>
        <div class="inv-subtitle">- CREDIT</div>
        <div class="inv-sep"></div>
        <table style="font-size: 10px; margin-top: 2px;">
          <tr>
            <td style="font-weight: bold; padding: 1px 4px;">Invoice No</td>
            <td style="font-weight: bold; text-align: right; padding: 1px 4px;">{{ $billNo }}</td>
          </tr>
          <tr>
            <td style="font-weight: bold; padding: 1px 4px;">Invoice Date</td>
            <td style="font-weight: bold; text-align: right; padding: 1px 4px;">{{ \Carbon\Carbon::parse($billDate)->format('d-m-Y') }}</td>
          </tr>
          <tr>
            <td style="padding: 1px 4px;">Salesman :</td>
            <td style="text-align: right; padding: 1px 4px;">COUNTER SALE</td>
          </tr>
          <tr>
            <td style="padding: 1px 4px;">Order No.</td>
            <td style="text-align: right; padding: 1px 4px;">-</td>
          </tr>
        </table>
      </td>
      
      <!-- Right Column: Customer Details -->
      <td style="width: 30%; padding: 4px;">
        <table style="border: none;">
          <tr>
            <td style="border: none; width: 80%;">
              <div class="customer-label">Customer Name & Address :</div>
            </td>
            <td style="border: none; width: 20%; text-align: right;">
              <span style="background: #999; color: #fff; padding: 2px 10px; font-weight: bold; font-size: 10px;">COPY</span>
            </td>
          </tr>
        </table>
        
        <div class="customer-name">{{ $customerName }}</div>
        <div class="customer-addr">
          <span class="ph">{{ $custAddress }}</span><br>
          <span class="ph">{{ $custCity }}</span><br>
          Phone : <span class="ph">{{ $custPhone }}</span><br>
          <b>GST IN &nbsp;&nbsp; <span class="ph">{{ $custGst }}</span> &nbsp;&nbsp; KERALA</b><br>
          <div style="font-size:8px; margin-top:2px;">Dl.Nos &nbsp;&nbsp;&nbsp;&nbsp; <span class="ph">-</span></div>
        </div>
      </td>
    </tr>
  </table>

  <!-- E-Way Table -->
  <table class="eway-table">
    <tr>
      <td style="width: 33%;">E-way No :</td>
      <td style="width: 33%;">Vehicle No :</td>
      <td style="width: 34%;">IRN :</td>
    </tr>
  </table>

  <!-- Items Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th style="width:5%;">M.R.P</th>
        <th style="width:6%;">BATCH NO</th>
        <th style="width:4%;">EXP<br>DATE</th>
        <th style="width:4%;">MFR/<br>MKT</th>
        <th style="width:23%;">ITEM NAME & PACKING</th>
        <th style="width:6%;">HSN<br>CODE</th>
        <th style="width:3%;">QTY</th>
        <th style="width:3%;">FREE</th>
        <th style="width:5%;">RATE</th>
        <th style="width:6%;">AMOUNT</th>
        <th style="width:3%;">SCH<br>DIS%</th>
        <th style="width:3%;">CASH<br>DIS%</th>
        <th style="width:5%;">DISC<br>.AMOUNT</th>
        <th style="width:6%;">TAXABLE<br>AMOUNT</th>
        <th style="width:3%;">GST<br>%</th>
        <th style="width:6%;">GST<br>VALAUE</th>
        <th style="width:6%;">NET<br>AMOUNT</th>
      </tr>
    </thead>
    <tbody>
      @php 
        $itemCount = count($items);
        $totalItems = $itemCount;
        $maxRows = 12; // Adjust for landscape to hit bottom perfectly
        $fillerRows = max(1, $maxRows - $itemCount);
      @endphp
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
      @for($i = 0; $i < $fillerRows; $i++)
        <tr class="empty-row">
          <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
          <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
      @endfor
    </tbody>
  </table>

  <!-- Footer Wrapper -->
  <div class="footer-wrapper">
    <div class="msg-row">Message : THIS BILL IS FOR RECORD PURPOSE ONLY.</div>
    
    <table class="footer-blocks" style="border: none;">
      <tr>
        <!-- Bank & GST -->
        <td style="width: 75%; border-right: 1px solid #000; padding: 0;">
          <table style="width: 100%; border: none;">
            <tr>
              <td style="width: 40%; border: none; border-right: 1px dotted #000; vertical-align: top;">
                <div class="bank-details">
                  <div class="bank-title">Bank Details</div>
                  <table style="border: none; width: 100%;">
                    <tr>
                      <td style="border: none; width: 60%; line-height: 1.4;">
                        Bank : HDFC Bank<br>Br : Palace Road, Tcr<br>A.c No : 50200043969997<br>IFSC : HDFC0000057
                      </td>
                      <td style="border: none; width: 40%; text-align: right;">
                        <img src="https://chart.googleapis.com/chart?chs=60x60&cht=qr&chl=upi://pay?pa=leo@hdfcbank" style="width:50px; height:50px;" alt="QR">
                      </td>
                    </tr>
                  </table>
                </div>
              </td>
              <td style="width: 60%; border: none; padding: 2px;">
                <table class="gst-slab">
                  <thead><tr><th>GST%</th><th>TAXABLE</th><th>CGST</th><th>SGST</th><th>IGST</th><th>TOTALGST</th></tr></thead>
                  <tbody>
                    @foreach($gstBreakup as $rate => $g)
                      <tr>
                        <td style="text-align: center;">{{ $rate }}%</td>
                        <td>{{ number_format($g['taxable'], 2) }}</td>
                        <td>{{ number_format($g['cgst'], 2) }}</td>
                        <td>{{ number_format($g['sgst'], 2) }}</td>
                        <td>{{ number_format($g['igst'], 2) }}</td>
                        <td>-</td>
                      </tr>
                    @endforeach
                    @if(count($gstBreakup) == 0)
                      <tr><td style="text-align: center;">-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr>
                    @endif
                  </tbody>
                </table>
                <table class="prep-table">
                  <tr>
                    <td style="width: 30%;"><b>PL.NO :</b> <span class="ph">-</span></td>
                    <td style="width: 40%;"><b>Route :</b> <span class="ph">COUNTER</span></td>
                    <td style="width: 30%;"><b>Item Total:</b> <span class="ph">{{ $totalItems }}</span></td>
                  </tr>
                  <tr>
                    <td>Prepared by :</td>
                    <td>-</td>
                    <td>Qty Total: <span class="ph">-</span></td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
          <div class="declaration">
            <b>Declaration :</b> We hereby warranty that the medicine purchased under this memo do not contravene in any way the provision of Section 18 of the Drug & Cosmetics Act 1940.<br>
            (1) Goods once sold will not be taken back.(2) Subject to Thrissur jurisdiction only.(3) Interest @18% on overdue payments.(4) Please check Batchno & MRP on NPPA products on delivery.(5) We do not take any responsibility for losses of goods.(6) Payments made in cash should always be against duly signed receipts
          </div>
        </td>
        
        <!-- Totals -->
        <td style="width: 25%; padding: 0;">
          <table class="totals-table">
            <tr><td>Gross Amount</td><td class="ph">{{ number_format($subtotal, 2) }}</td></tr>
            <tr><td>Discount Amount</td><td class="ph">0.00</td></tr>
            <tr><td>Taxable Amount</td><td class="ph">{{ number_format($subtotal, 2) }}</td></tr>
            <tr><td>CGST Amount</td><td class="ph">{{ number_format($totalGst / 2, 2) }}</td></tr>
            <tr><td>SGST Amount</td><td class="ph">{{ number_format($totalGst / 2, 2) }}</td></tr>
            <tr><td>IGST Amount</td><td class="ph">0.00</td></tr>
            <tr><td>TCS @ 0.1</td><td class="ph">0.00</td></tr>
            <tr><td>Debit Note</td><td class="ph"></td></tr>
            <tr><td>Round off</td><td class="ph">{{ number_format($grandTotal - ($subtotal + $totalGst), 2) }}</td></tr>
            <tr class="bill-amt-row">
              <td>Bill Amount  ₹</td>
              <td>{{ number_format($grandTotal, 2) }}</td>
            </tr>
          </table>
          <div style="text-align: center; font-weight: bold; font-size: 8px; margin-top: 5px;">For LEO PHARMA<br>DISTRIBUTORS P.LTD</div>
        </td>
      </tr>
    </table>
  </div>
  
  <table class="bottom-row">
    <tr>
      <td style="width: 75%;">Powered by : Green Software Solutions, Ph : +91 9446760469</td>
      <td style="width: 25%; text-align: right;"><b>E & OE</b> &nbsp;&nbsp;&nbsp;&nbsp; Page 1 of 1</td>
    </tr>
  </table>
  
</div>
</body>
</html>