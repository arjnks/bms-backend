<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderLog extends Model
{
    protected $fillable = [
        'bill_id',
        'customer_id',
        'rule_id',
        'channel',
        'status',
        'sent_at',
        'error_msg',
    ];
}
