<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpBillStatus extends Model
{
    use HasFactory;

    protected $primaryKey = 'billno';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'billno',
        'date',
        'cucode',
        'cuname',
        'netamount',
        'amtreceived',
        'settled',
        'ddays',
        'lockdays',
    ];
}
