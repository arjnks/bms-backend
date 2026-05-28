<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'customer_code',
        'gstin',
        'credit_limit',
        'external_cucode',
        'preferred_bill_format',
        'salesperson_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function reminderLogs()
    {
        return $this->hasMany(ReminderLog::class);
    }
}
