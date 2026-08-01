<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\ProductDisplay;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected const RELATED_LIMIT = 8;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'display_category' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = ProductDisplay::query()
            ->with('item')
            ->where('is_published', true)
            ->when($validated['display_category'] ?? null, fn ($q, $v) => $q->where('display_category', $v))
            ->when($validated['brand'] ?? null, fn ($q, $v) => $q->where('brand', $v))
            ->when($validated['q'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('display_name', 'ilike', "%{$term}%")
                    ->orWhereHas('item', fn ($q) => $q->where('name', 'ilike', "%{$term}%"));
            }))
            ->when($validated['min_price'] ?? null, fn ($q, $v) => $q->whereHas('item', fn ($q) => $q->where('unit_price', '>=', $v)))
            ->when($validated['max_price'] ?? null, fn ($q, $v) => $q->whereHas('item', fn ($q) => $q->where('unit_price', '<=', $v)))
            ->orderBy('sort_order')
            ->paginate($validated['per_page'] ?? 20);

        return ProductResource::collection($products);
    }

    public function show(ProductDisplay $product)
    {
        abort_unless($product->is_published, 404);

        return new ProductResource($product->load('item'));
    }

    public function related(ProductDisplay $product)
    {
        abort_unless($product->is_published, 404);

        if (! $product->display_category) {
            return ProductResource::collection(collect());
        }

        $related = ProductDisplay::query()
            ->with('item')
            ->where('is_published', true)
            ->where('display_category', $product->display_category)
            ->where('id', '!=', $product->id)
            ->orderBy('sort_order')
            ->limit(self::RELATED_LIMIT)
            ->get();

        return ProductResource::collection($related);
    }

    public function categories()
    {
        $categories = ProductDisplay::where('is_published', true)
            ->whereNotNull('display_category')
            ->distinct()
            ->orderBy('display_category')
            ->pluck('display_category');

        return response()->json(['data' => $categories]);
    }
}
