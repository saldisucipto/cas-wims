<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmsAccount extends Model
{
    protected $fillable = [
        'username',
        'password',
        'function',
        'status',
        'daily_worker_id',
    ];

    public function dailyWorker()
    {
        return $this->belongsTo(DailyWorker::class, 'daily_worker_id');
    }

    public function workingSessions()
    {
        return $this->hasMany(WorkingSession::class);
    }

    public function packingStations()
    {
        return $this->hasMany(PackingStation::class);
    }

    public function rfDevices()
    {
        return $this->hasMany(RfDevice::class);
    }
}
