<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_type',
        'ready_quantity',
    ];

    protected function casts(): array
    {
        return [
            'ready_quantity' => 'integer',
        ];
    }
}
