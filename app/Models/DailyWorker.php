<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWorker extends Model
{
    protected $fillable = [
        'employee_code',
        'name',
        'function',
        'position',
        'status',
        'division',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function workingSessions()
    {
        return $this->hasMany(WorkingSession::class);
    }

    public function consumableRequests()
    {
        return $this->hasMany(ConsumableRequest::class);
    }
}
