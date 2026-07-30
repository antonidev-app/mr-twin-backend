<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(LocalOrder::class, 'local_order_id');
    }

    public function item()
    {
        return $this->belongsTo(SyncedItem::class, 'item_id');
    }
}
