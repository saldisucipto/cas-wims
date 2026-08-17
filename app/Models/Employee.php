<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'employee_name',
        'division_id',
        'department_id',
        'position_id',
        'employment_type',
        'employment_start_date',
        'employment_end_date',
        'status',
        'shift_pattern',
        'phone',
        'email',
        'notes',
        'user_id',
        'created_by',
        'updated_by',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
