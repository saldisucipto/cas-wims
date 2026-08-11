<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtkItem extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'minimum_stock',
        'current_stock',
        'status',
        'notes',
    ];

    public function requestItems()
    {
        return $this->hasMany(AtkRequestItem::class);
    }

    public function stockTransactions()
    {
        return $this->hasMany(AtkStockTransaction::class);
    }
}
