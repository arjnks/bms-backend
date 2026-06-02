<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leo Pharma Distributors – Tax Invoice</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; background: #fff; color: #000; }
  .page { width: 100%; padding: 0; margin: 0 auto; background: #fff; }
  table { width: 100%; border-collapse: collapse; }
  td { vertical-align: top; }
  
  .cname { font-size: 12px; font-weight: bold; }
  .caddr { font-size: 8.5px; line-height: 1.45; }
  .cgst { font-size: 9px; font-weight: bold; }
  .inv-title { font-size: 14px; font-weight: bold; text-align: center; }
  .inv-subtitle { font-size: 10px; text-align: center; margin-bottom: 4px; }
  .inv-sep { width: 100%; border-top: 1px solid #000; margin: 2px 0; }
  .customer-label { font-size: 8.5px; font-style: italic; text-decoration: underline; margin-bottom: 2px; }
  .customer-name { font-size: 11px; font-weight: bold; }
  .customer-addr { font-size: 9px; line-height: 1.45; }
  
  table.items th { border: 0.5px solid #555; padding: 2px; font-size: 8px; font-weight: bold; text-align: center; vertical-align: middle; }
  table.items td { border: 0.5px solid #999; padding: 1.5px 2px; font-size: 8.5px; text-align: center; vertical-align: middle; }
  table.items td.left { text-align: left; }
  table.items .empty-row td { height: 13px; border-color: #ccc; }
  
  .msg-row { font-size: 9px; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 3px; }
  .bl { font-size: 9px; font-weight: bold; border-bottom: 1px solid #000; margin-bottom: 2px; }
  .bt { font-size: 8.5px; line-height: 1.5; }
  table.gst-slab th { border: 0.5px solid #666; padding: 1px 3px; font-weight: bold; text-align: center; font-size: 8px;}
  table.gst-slab td { border: 0.5px solid #999; padding: 1px 3px; text-align: right; font-size: 8px;}
  table.gst-slab td:first-child { text-align: center; }
  
  .meta-ops { margin-top: 4px; font-size: 8.5px; line-height: 1.7; }
  .declaration { font-size: 7.5px; line-height: 1.45; margin-top: 4px; border-top: 1px solid #000; padding-top: 3px; }
  
  table.totals td { padding: 1.5px 4px; border: none; font-size: 8.5px;}
  table.totals td:last-child { text-align: right; }
  .bill-amt-row td { font-size: 11px; font-weight: bold; border-top: 2px solid #000 !important; }
  .sig-row { padding: 3px 5px; font-size: 9px; text-align: right; font-weight: bold; line-height: 1.6; }
  
  .ph { color: #1a56db; font-style: italic; }
</style>
</head>
<body>
<div class="page">
  
  <!-- Header Top -->
  <table style="border: 1px solid #000; border-bottom: none;">
    <tr>
      <td style="width: 10%; border-right: 1px solid #000; text-align: center; vertical-align: middle; padding: 4px;">
        <!-- Logo placeholder -->
      </td>
      <td style="width: 32%; border-right: 1px solid #000; padding: 3px 5px;">
        <div class="cname">LEO PHARMA DISTRIBUTORS P.LTD</div>
        <div class="caddr">DOOR NO.17/18/C, LEO LOGISTICS HUB<br>ELAMTHURUTHY, KALADY, KUTTANELLUR P.O<br>THRISSUR – 680 014<br>PHONE : 0487 2224080, 3506700<br>e-mail : order.leogroup@gmail.com</div>
        <div class="cgst">GST IN :  32AALCA0738P1ZG - Kerala(32)</div>
      </td>
      <td style="width: 25%; border-right: 1px solid #000; padding: 3px 6px; text-align: center;">
        <div class="inv-title">TAX INVOICE</div>
        <div class="inv-sep"></div>
        <div class="inv-subtitle">- CREDIT</div>
        <div class="inv-sep"></div>
        <table style="font-size: 9px;">
          <tr><td style="font-weight: bold; text-align: left;">Invoice No</td><td class="ph" style="text-align: right; font-weight: bold;">{{ $bill->invoice_no }}</td></tr>
          <tr><td style="font-weight: bold; text-align: left;">Invoice Date</td><td class="ph" style="text-align: right; font-weight: bold;">{{ optional($bill->bill_date)->format('d-m-Y') }}</td></tr>
          <tr><td style="font-weight: bold; text-align: left;">Due Date</td><td class="ph" style="text-align: right; font-weight: bold;">{{ optional($bill->due_date)->format('d-m-Y') }}</td></tr>
        </table>
      </td>
      <td style="width: 28%; padding: 3px 5px;">
        <div class="customer-label">Customer Name & Address :</div>
        <div class="customer-name ph">{{ optional($bill->customer->user)->name }}</div>
        <div class="customer-addr">
          <span class="ph">{{ optional($bill->customer)->address }}</span><br>
          <span class="ph">{{ optional($bill->customer)->city }}</span><br>
          Phone : <span class="ph">{{ optional($bill->customer)->phone_whatsapp }}</span><br>
          <b>GST IN</b> <span class="ph">{{ optional($bill->customer)->gstin }}</span>
        </div>
      </td>
      <td style="width: 5%; border-left: 1px solid #000; background: #d0d0d0; text-align: center; padding-top: 4px;">
        <span style="font-weight: bold; font-size: 10px;">COPY</span>
      </td>
    </tr>
  </table>

  <!-- Header Bottom -->
  <table style="border: 1px solid #000; border-bottom: none; font-size: 9px;">
    <tr>
      <td style="width: 33%; padding: 2px 5px;">E-way No : <span class="ph">-</span></td>
      <td style="width: 33%; padding: 2px 5px;">Vehicle No : <span class="ph">-</span></td>
      <td style="width: 34%; padding: 2px 5px;"><b>IRN :</b> <span class="ph">-</span></td>
    </tr>
  </table>

  <!-- Items Table -->
  <table class="items" style="border: 1px solid #000; border-bottom: none;">
    <thead>
      <tr>
        <th style="width: 6%;">M.R.P</th><th style="width: 8%;">BATCH NO</th><th style="width: 5%;">EXP<br>DATE</th><th style="width: 5%;">MFR/<br>MKT</th>
        <th style="width: 20%;">ITEM NAME & PACKING</th><th style="width: 8%;">HSN<br>CODE</th><th style="width: 4%;">QTY</th><th style="width: 4%;">FREE</th>
        <th style="width: 6%;">RATE</th><th style="width: 6%;">AMOUNT</th><th style="width: 4%;">SCH<br>DIS%</th><th style="width: 4%;">CASH<br>DIS%</th>
        <th style="width: 6%;">DISC<br>.AMT</th><th style="width: 6%;">TAXABLE<br>AMT</th><th style="width: 3%;">GST<br>%</th><th style="width: 5%;">GST<br>VAL</th><th style="width: 6%;">NET<br>AMT</th>
      </tr>
    </thead>
    <tbody>
      @foreach($bill->lineItems as $item)
      <tr>
        <td class="ph">-</td>
        <td class="ph">-</td>
        <td class="ph">-</td>
        <td class="ph">-</td>
        <td class="left ph">{{ $item->product_name }}</td>
        <td class="ph">{{ $item->hsn_code }}</td>
        <td class="ph">{{ $item->qty }}</td>
        <td class="ph">-</td>
        <td class="ph">{{ number_format($item->rate, 2) }}</td>
        <td class="ph">{{ number_format($item->qty * $item->rate, 2) }}</td>
        <td class="ph">-</td>
        <td class="ph">-</td>
        <td class="ph">-</td>
        <td class="ph">{{ number_format($item->qty * $item->rate, 2) }}</td>
        <td class="ph">{{ $item->gst_pct }}</td>
        <td class="ph">{{ number_format(($item->qty * $item->rate) * ($item->gst_pct / 100), 2) }}</td>
        <td class="ph">{{ number_format($item->line_total, 2) }}</td>
      </tr>
      @endforeach
      @for($i = 0; $i < max(0, 10 - $bill->lineItems->count()); $i++)
      <tr class="empty-row"><td colspan="17"></td></tr>
      @endfor
    </tbody>
  </table>

  <!-- Footer -->
  <table style="border: 1px solid #000; border-top: none;">
    <tr>
      <td style="width: 75%; border-right: 1px solid #000; padding: 3px 5px;">
        <div class="msg-row">Message : <span class="ph">-</span></div>
        
        <!-- Bank & GST nested table -->
        <table>
          <tr>
            <td style="width: 30%; border-right: 1px solid #000; padding-right: 3px;">
              <div class="bl">Bank Details</div>
              <div class="bt">Bank : HDFC Bank<br>Br : Palace Road, Tcr<br>A.c No : 50200043969997<br>IFSC : HDFC0000057</div>
            </td>
            <td style="width: 10%; border-right: 1px solid #000; text-align: center; vertical-align: middle;">
              <div style="width:40px;height:40px;border:1px solid #999;margin:0 auto;line-height:40px;font-size:7px;color:#999;">QR</div>
            </td>
            <td style="width: 60%; padding-left: 4px;">
              <table class="gst-slab">
                <thead><tr><th>GST%</th><th>TAXABLE</th><th>CGST</th><th>SGST</th><th>IGST</th><th>TOTALGST</th></tr></thead>
                <tbody>
                  <tr><td>Total</td><td class="ph">{{ number_format($bill->subtotal, 2) }}</td><td class="ph">{{ number_format($bill->gst_total / 2, 2) }}</td><td class="ph">{{ number_format($bill->gst_total / 2, 2) }}</td><td class="ph">0.00</td><td class="ph">{{ number_format($bill->gst_total, 2) }}</td></tr>
                </tbody>
              </table>
            </td>
          </tr>
        </table>
        
        <div class="meta-ops">Item Total <span class="ph">{{ $bill->lineItems->count() }}</span></div>
        <div class="declaration"><b>Declaration</b> : We hereby warrenty that the medicine purchased under this memo do not contravene in any way the provision of Section 18 of the Drug & Cosmetics Act 1940.<br>(1) Goods once sold will not be taken back.(2) Subject to Thrissur jurisdiction only.(3) Interest @18% on overdue payments.(4) Please check Batchno & MRP on NPPA products on delivery.(5) We do not take any responsibility for losses of goods.(6) Payments made in cash should always be against duly signed receipts</div>
      </td>
      
      <td style="width: 25%; padding: 0;">
        <table class="totals">
          <tr><td>Taxable Amount</td><td class="ph">{{ number_format($bill->subtotal, 2) }}</td></tr>
          <tr><td>CGST Amount</td><td class="ph">{{ number_format($bill->gst_total / 2, 2) }}</td></tr>
          <tr><td>SGST Amount</td><td class="ph">{{ number_format($bill->gst_total / 2, 2) }}</td></tr>
          <tr><td>IGST Amount</td><td class="ph">0.00</td></tr>
          <tr><td>&nbsp;</td><td></td></tr>
          <tr><td>&nbsp;</td><td></td></tr>
          <tr><td>&nbsp;</td><td></td></tr>
          <tr class="bill-amt-row"><td><b>Bill Amount  ₹</b></td><td class="ph"><b>{{ number_format($bill->grand_total, 2) }}</b></td></tr>
        </table>
        <br><br><br>
        <div class="sig-row">For LEO PHARMA<br>DISTRIBUTORS P.LTD</div>
      </td>
    </tr>
  </table>
  
  <table style="border: 1px solid #000; border-top: none; font-size: 8px;">
    <tr>
      <td style="padding: 2px 5px;"><b>RS in words :</b> <span class="ph">Rupees {{ number_format($bill->grand_total, 2) }}</span></td>
      <td style="padding: 2px 5px; text-align: right;"><b>E & OE</b></td>
    </tr>
  </table>
  
  <table style="border: 1px solid #000; border-top: none; font-size: 7.5px;">
    <tr>
      <td style="padding: 2px 5px;">Powered by : Green Software Solutions, Ph : +91 9446760469</td>
      <td style="padding: 2px 5px; text-align: right;">Page 1 of 1</td>
    </tr>
  </table>
  
</div>
</body>
</html>
