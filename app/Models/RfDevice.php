<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfDevice extends Model
{
    protected $fillable = [
        'code',
        'status',
        'wms_account_id',
    ];

    public function workingSessions()
    {
        return $this->hasMany(WorkingSession::class);
    }

    public function consumableRequests()
    {
        return $this->hasMany(ConsumableRequest::class);
    }

    public function wmsAccount()
    {
        return $this->belongsTo(WmsAccount::class);
    }
}
