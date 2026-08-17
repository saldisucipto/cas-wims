<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkingCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'day_name',
        'is_working_day',
        'working_hours',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_working_day' => 'boolean',
            'working_hours' => 'float',
        ];
    }
}
