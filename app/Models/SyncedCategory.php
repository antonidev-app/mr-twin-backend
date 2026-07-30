<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncedCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
