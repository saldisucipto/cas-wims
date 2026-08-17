<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'break_minutes',
        'effective_hours',
        'is_short_day',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'effective_hours' => 'float',
            'is_short_day' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Map a short-shift code back to its base shift band (S1_SAT -> S1).
     */
    public function band(): string
    {
        return str_ends_with($this->code, '_SAT') ? substr($this->code, 0, 2) : $this->code;
    }
}
