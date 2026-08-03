<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableRequest extends Model
{
    protected $fillable = [
        'request_number',
        'working_session_id',
        'daily_worker_id',
        'rf_device_id',
        'notes',
        'status',
        'requested_at',
        'validated_at',
        'rejected_at',
        'validated_by',
        'rejected_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'validated_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function workingSession()
    {
        return $this->belongsTo(WorkingSession::class);
    }

    public function dailyWorker()
    {
        return $this->belongsTo(DailyWorker::class);
    }

    public function rfDevice()
    {
        return $this->belongsTo(RfDevice::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items()
    {
        return $this->hasMany(ConsumableRequestItem::class);
    }
}
