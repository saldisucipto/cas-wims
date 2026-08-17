<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_number',
        'month',
        'year',
        'status',
        'manpower_planning_id',
        'notes',
        'created_by',
        'updated_by',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function details()
    {
        return $this->hasMany(ShiftScheduleDetail::class);
    }

    public function handovers()
    {
        return $this->hasMany(ShiftHandover::class);
    }

    public function manpowerPlanning()
    {
        return $this->belongsTo(ManpowerPlanning::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
