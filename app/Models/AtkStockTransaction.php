<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtkStockTransaction extends Model
{
    protected $fillable = [
        'atk_item_id',
        'transaction_number',
        'transaction_type',
        'reference',
        'supplier',
        'quantity_in',
        'quantity_out',
        'balance',
        'notes',
        'performed_by',
        'taken_by_name',
        'transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_at' => 'datetime',
        ];
    }

    public function atkItem()
    {
        return $this->belongsTo(AtkItem::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
