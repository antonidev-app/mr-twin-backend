<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Ai\AiEnrichmentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductAdminResource;
use App\Models\ProductDisplay;
use App\Models\SyncedItem;
use App\Services\Ai\OpenAiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'suspended' => ['nullable', 'boolean'],
            'item_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = SyncedItem::query()
            ->with('productDisplay')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q');
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'ilike', "%{$term}%")->orWhere('sku', 'ilike', "%{$term}%");
                });
            })
            ->when($request->has('is_published'), function ($query) use ($request) {
                if ($request->boolean('is_published')) {
                    $query->whereHas('productDisplay', fn ($q) => $q->where('is_published', true));
                } else {
                    $query->where(function ($query) {
                        $query->whereDoesntHave('productDisplay')
                            ->orWhereHas('productDisplay', fn ($q) => $q->where('is_published', false));
                    });
                }
            })
            ->when($request->has('suspended'), fn ($query) => $query->where('suspended', $request->boolean('suspended')))
            ->when($request->filled('item_type'), fn ($query) => $query->where('item_type', $request->string('item_type')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ProductAdminResource::collection($items);
    }

    public function show(SyncedItem $item)
    {
        return new ProductAdminResource($item->load('productDisplay'));
    }

    public function update(Request $request, SyncedItem $item)
    {
        $validated = $request->validate([
            'is_published' => ['required', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'display_category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        ProductDisplay::query()->updateOrCreate(['item_id' => $item->id], $validated);

        return new ProductAdminResource($item->fresh('productDisplay'));
    }

    public function aiDraft(SyncedItem $item, OpenAiClient $client)
    {
        try {
            $draft = $client->draftProduct($item);
        } catch (AiEnrichmentException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($draft);
    }

    public function uploadImage(Request $request, SyncedItem $item)
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $product = ProductDisplay::query()->firstOrCreate(['item_id' => $item->id]);

        $path = $request->file('image')->store("products/{$item->id}", 'public');
        $url = Storage::disk('public')->url($path);

        $images = $product->images ?? [];
        $images[] = $url;
        $product->update(['images' => $images]);

        return new ProductAdminResource($item->fresh('productDisplay'));
    }

    public function deleteImage(Request $request, SyncedItem $item)
    {
        $validated = $request->validate(['url' => ['required', 'string']]);

        $product = $item->productDisplay;
        abort_if(! $product, 404);

        $images = array_values(array_filter(
            $product->images ?? [],
            fn ($url) => $url !== $validated['url'],
        ));
        $product->update(['images' => $images]);

        Storage::disk('public')->delete(Str::after($validated['url'], '/storage/'));

        return new ProductAdminResource($item->fresh('productDisplay'));
    }
}
