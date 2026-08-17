<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesonTransaction extends Model
{
    protected $fillable = [
        'warehouse_id',
        'transaction_id',
        'transaction_type',
        'doc_type',
        'document_number',
        'doc_line_no',
        'status',
        'transaction_time',
        'customer_id_fm',
        'sku_fm',
        'lotnum_fm',
        'location_fm',
        'fm_muid',
        'id_fm',
        'pack_id_fm',
        'uom_fm',
        'qty_fm',
        'qty_each_fm',
        'customer_id_to',
        'sku_to',
        'lotnum_to',
        'location_to',
        'to_muid',
        'id_to',
        'pack_id_to',
        'uom_to',
        'qty_to',
        'qty_each_to',
        'total_price',
        'total_net_weight',
        'total_gross_weight',
        'total_cubic',
        'udf01',
        'udf02',
        'udf03',
        'udf04',
        'udf05',
        'system_time',
        'operator_id',
        'operator_username',
        'system_operator',
    ];

    protected function casts(): array
    {
        return [
            'transaction_time' => 'datetime',
            'system_time' => 'datetime',
            'qty_fm' => 'float',
            'qty_each_fm' => 'float',
            'qty_to' => 'float',
            'qty_each_to' => 'float',
            'total_price' => 'float',
            'total_net_weight' => 'float',
            'total_gross_weight' => 'float',
            'total_cubic' => 'float',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(WmsAccount::class, 'operator_id');
    }
}
