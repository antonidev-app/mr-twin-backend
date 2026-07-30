<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->effective_name,
            'sku' => $this->item->sku,
            'price' => (float) $this->item->unit_price,
            'stock' => (float) $this->item->stock,
            'description' => $this->description,
            'images' => $this->images ?? [],
            'display_category' => $this->display_category,
            'brand' => $this->brand,
        ];
    }
}
