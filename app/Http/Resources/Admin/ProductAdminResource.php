<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accurate_id' => $this->accurate_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit_price' => (float) $this->unit_price,
            'stock' => (float) $this->stock,
            'item_type' => $this->item_type,
            'suspended' => $this->suspended,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'product_display' => $this->whenLoaded('productDisplay', fn () => $this->productDisplay ? [
                'is_published' => $this->productDisplay->is_published,
                'display_name' => $this->productDisplay->display_name,
                'description' => $this->productDisplay->description,
                'images' => $this->productDisplay->images ?? [],
                'display_category' => $this->productDisplay->display_category,
                'brand' => $this->productDisplay->brand,
                'sort_order' => $this->productDisplay->sort_order,
            ] : null),
        ];
    }
}
