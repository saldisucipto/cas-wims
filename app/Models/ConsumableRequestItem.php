<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableRequestItem extends Model
{
    protected $fillable = [
        'consumable_request_id',
        'consumable_id',
        'quantity',
    ];

    public function consumableRequest()
    {
        return $this->belongsTo(ConsumableRequest::class);
    }

    public function consumable()
    {
        return $this->belongsTo(Consumable::class);
    }
}
