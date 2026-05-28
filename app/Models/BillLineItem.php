<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillLineItem extends Model
{
    protected $fillable = [
        'bill_id',
        'product_name',
        'hsn_code',
        'qty',
        'unit',
        'rate',
        'gst_pct',
        'line_total',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
