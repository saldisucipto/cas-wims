<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerPlanningItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'manpower_planning_id',
        'division',
        'name',
        'code',
        'workload_source',
        'workload',
        'workload_unit',
        'productivity_per_hour',
        'productivity_unit',
        'manpower_type',
        'device_type',
        'allowed_shifts',
        'start_time',
        'end_time',
        'minimum_shift',
        'division_reason',
        'minimum_manpower',
        'shift_duration',
        'non_productive_hours',
        'effective_working_hours',
        'required_mpp',
        'mpp_per_shift',
        'number_of_shift',
        'available_mpp',
        'feasibility_status',
        'bottleneck',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'workload' => 'float',
            'productivity_per_hour' => 'float',
            'shift_duration' => 'float',
            'non_productive_hours' => 'float',
            'effective_working_hours' => 'float',
            'minimum_manpower' => 'integer',
            'minimum_shift' => 'integer',
            'required_mpp' => 'integer',
            'mpp_shift_1' => 'integer',
            'mpp_shift_2' => 'integer',
            'mpp_per_shift' => 'integer',
            'number_of_shift' => 'integer',
            'available_mpp' => 'integer',
            'sort_order' => 'integer',
            'bottleneck' => 'boolean',
        ];
    }

    public function planning()
    {
        return $this->belongsTo(ManpowerPlanning::class);
    }
}
