<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerPlanning extends Model
{
    use HasFactory;

    protected $fillable = [
        'planning_number',
        'planning_date',
        'inbound_volume',
        'outbound_volume',
        'vas_volume',
        'shift_duration',
        'non_productive_hours',
        'effective_working_hours',
        'total_mpp',
        'recommendation',
        'overall_status',
        'status',
        'revision',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'inbound_volume' => 'integer',
            'outbound_volume' => 'integer',
            'vas_volume' => 'integer',
            'shift_duration' => 'float',
            'non_productive_hours' => 'float',
            'effective_working_hours' => 'float',
            'total_mpp' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function items()
    {
        return $this->hasMany(ManpowerPlanningItem::class);
    }

    public function devices()
    {
        return $this->hasMany(ManpowerPlanningDevice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
