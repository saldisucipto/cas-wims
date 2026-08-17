<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftScheduleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_schedule_id',
        'employee_id',
        'date',
        'week_number',
        'shift',
        'position_id',
        'division_id',
        'working_hours',
        'assignment_type',
        'is_override',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'working_hours' => 'float',
            'is_override' => 'boolean',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
