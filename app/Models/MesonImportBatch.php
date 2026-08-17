<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesonImportBatch extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'file_name',
        'total_rows',
        'valid_rows',
        'imported_rows',
        'duplicate_rows',
        'invalid_operator_rows',
        'status',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'invalid_operator_rows' => 'integer',
        ];
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
