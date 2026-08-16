<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerVasSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_date',
        'volume',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'integer',
        ];
    }
}
