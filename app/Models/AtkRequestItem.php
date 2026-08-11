<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtkRequestItem extends Model
{
    protected $fillable = [
        'atk_request_id',
        'atk_item_id',
        'quantity',
    ];

    public function atkRequest()
    {
        return $this->belongsTo(AtkRequest::class);
    }

    public function atkItem()
    {
        return $this->belongsTo(AtkItem::class);
    }
}
