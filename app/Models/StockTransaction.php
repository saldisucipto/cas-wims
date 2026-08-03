<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'consumable_id',
        'transaction_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'notes',
        'performed_by',
        'transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_at' => 'datetime',
        ];
    }

    public function consumable()
    {
        return $this->belongsTo(Consumable::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
