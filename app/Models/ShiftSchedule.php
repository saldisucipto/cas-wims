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
        'period_start_date',
        'period_end_date',
        'status',
        'division_id',
        'selected_employee_ids',
        'initial_shift_map',
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
            'period_start_date' => 'date',
            'period_end_date' => 'date',
            'selected_employee_ids' => 'array',
            'initial_shift_map' => 'array',
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

    public function division()
    {
        return $this->belongsTo(Division::class);
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
