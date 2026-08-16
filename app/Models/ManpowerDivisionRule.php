<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManpowerDivisionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'division',
        'minimum_shift',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'minimum_shift' => 'integer',
        ];
    }
}
