<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = [
        'trigger_type',
        'offset_days',
        'send_time',
        'channel',
        'message_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
