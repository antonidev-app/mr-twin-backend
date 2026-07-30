<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(LocalOrderItem::class);
    }
}
