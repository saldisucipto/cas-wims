<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManpowerPlanningRecommendation extends Model
{
    protected $fillable = [
        'manpower_planning_id',
        'role',
        'sort_order',
        'daily_worker_id',
        'employee_code',
        'name',
        'function',
        'orders',
        'days_worked',
        'orders_per_hour',
        'capacity',
        'allocated_orders',
        'utilization',
    ];

    protected function casts(): array
    {
        return [
            'orders' => 'integer',
            'days_worked' => 'integer',
            'orders_per_hour' => 'float',
            'capacity' => 'float',
            'allocated_orders' => 'integer',
            'utilization' => 'float',
        ];
    }

    public function planning()
    {
        return $this->belongsTo(ManpowerPlanning::class, 'manpower_planning_id');
    }

    public function dailyWorker()
    {
        return $this->belongsTo(DailyWorker::class);
    }
}
