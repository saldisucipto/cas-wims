<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'division',
        'name',
        'code',
        'workload_source',
        'workload_unit',
        'conversion_ratio',
        'productivity_per_hour',
        'productivity_unit',
        'manpower_type',
        'minimum_manpower',
        'available_manpower',
        'device_type',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_ratio' => 'float',
            'productivity_per_hour' => 'float',
            'minimum_manpower' => 'integer',
            'available_manpower' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
