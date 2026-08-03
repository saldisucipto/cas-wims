<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingStation extends Model
{
    protected $fillable = [
        'code',
        'station_number',
        'name',
        'qr_code',
        'status',
        'wms_account_id',
    ];

    public function workingSessions()
    {
        return $this->hasMany(WorkingSession::class);
    }

    public function wmsAccount()
    {
        return $this->belongsTo(WmsAccount::class);
    }
}
