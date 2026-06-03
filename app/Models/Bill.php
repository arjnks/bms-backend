<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_no',
        'bill_date',
        'due_date',
        'subtotal',
        'gst_total',
        'grand_total',
        'status',
        'payment_status',
        'payment_method',
        'utr_number',
        'proof_screenshot',
        'payment_submitted_at',
        'payment_verified_at',
        'rejection_reason',
        'bill_file_url',
        'bill_file_type',
        'uploaded_by',
        'amount_received',
        'is_settled',
        'aging_days',
        'lock_days',
    ];

    protected $casts = [
        'bill_date'             => 'date:Y-m-d',
        'due_date'              => 'date:Y-m-d',
        'payment_submitted_at'  => 'datetime',
        'payment_verified_at'   => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems()
    {
        return $this->hasMany(BillLineItem::class);
    }

    public function reminderLogs()
    {
        return $this->hasMany(ReminderLog::class);
    }
}
