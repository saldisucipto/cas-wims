<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftHandover extends Model
{
    use HasFactory;

    public const JOB_TYPES = ['Picking', 'Packing', 'QC', 'ReadyToShip', 'BCO', 'Other'];

    public const STATUSES = ['OPEN', 'TRANSFERRED', 'CLOSED'];

    protected $fillable = [
        'shift_schedule_id',
        'handover_date',
        'shift_from',
        'shift_to',
        'job_type',
        'description',
        'quantity',
        'unit',
        'status',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'quantity' => 'float',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftSchedule::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
