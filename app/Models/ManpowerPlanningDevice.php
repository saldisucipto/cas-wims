<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerPlanningDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'manpower_planning_id',
        'device_type',
        'ready_quantity',
        'required_one_shift',
        'required_per_shift',
        'physical_required',
        'shortage',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'ready_quantity' => 'integer',
            'required_one_shift' => 'integer',
            'required_per_shift' => 'integer',
            'physical_required' => 'integer',
            'shortage' => 'integer',
        ];
    }

    public function planning()
    {
        return $this->belongsTo(ManpowerPlanning::class);
    }
}
