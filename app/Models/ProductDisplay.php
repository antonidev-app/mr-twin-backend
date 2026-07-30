<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ProductDisplay extends Model
{
    protected $table = 'product_display';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'images' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function item()
    {
        return $this->belongsTo(SyncedItem::class, 'item_id');
    }

    protected function effectiveName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->display_name ?? $this->item?->name,
        );
    }
}
