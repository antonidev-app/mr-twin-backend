<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncedItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
            'suspended' => 'boolean',
            'unit_price' => 'decimal:2',
            'stock' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }
}
