<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_name' => $this->item_name,
            'sku' => $this->sku,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (float) $this->quantity,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
