<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingSession extends Model
{
    protected $fillable = [
        'daily_worker_id',
        'packing_station_id',
        'rf_device_id',
        'wms_account_id',
        'session_type',
        'status',
        'started_at',
        'ended_at',
        'close_type',
        'force_closed_by',
        'force_closed_at',
        'force_close_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'force_closed_at' => 'datetime',
        ];
    }

    public function dailyWorker()
    {
        return $this->belongsTo(DailyWorker::class);
    }

    public function packingStation()
    {
        return $this->belongsTo(PackingStation::class);
    }

    public function rfDevice()
    {
        return $this->belongsTo(RfDevice::class);
    }

    public function wmsAccount()
    {
        return $this->belongsTo(WmsAccount::class);
    }

    public function consumableRequests()
    {
        return $this->hasMany(ConsumableRequest::class);
    }

    public function forceCloser()
    {
        return $this->belongsTo(User::class, 'force_closed_by');
    }
}
