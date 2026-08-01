<?php

namespace Tests\Feature\Catalog;

use App\Models\ProductDisplay;
use App\Models\SyncedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $itemAttrs, array $displayAttrs): ProductDisplay
    {
        $item = SyncedItem::create($itemAttrs);

        return ProductDisplay::create(['item_id' => $item->id, ...$displayAttrs]);
    }

    public function test_it_returns_published_products_in_the_same_category(): void
    {
        $product = $this->makeProduct(
            ['accurate_id' => 1, 'sku' => 'A-1', 'name' => 'Laptop A'],
            ['is_published' => true, 'display_category' => 'Laptop & Komputer'],
        );

        $sameCategory = $this->makeProduct(
            ['accurate_id' => 2, 'sku' => 'A-2', 'name' => 'Laptop B'],
            ['is_published' => true, 'display_category' => 'Laptop & Komputer'],
        );

        $unpublished = $this->makeProduct(
            ['accurate_id' => 3, 'sku' => 'A-3', 'name' => 'Laptop C'],
            ['is_published' => false, 'display_category' => 'Laptop & Komputer'],
        );

        $otherCategory = $this->makeProduct(
            ['accurate_id' => 4, 'sku' => 'A-4', 'name' => 'Printer A'],
            ['is_published' => true, 'display_category' => 'Printer & Scanner'],
        );

        $response = $this->getJson("/api/catalog/products/{$product->id}/related");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($sameCategory->id));
        $this->assertFalse($ids->contains($product->id));
        $this->assertFalse($ids->contains($unpublished->id));
        $this->assertFalse($ids->contains($otherCategory->id));
    }

    public function test_it_returns_empty_when_product_has_no_display_category(): void
    {
        $product = $this->makeProduct(
            ['accurate_id' => 10, 'sku' => 'B-1', 'name' => 'Aksesoris A'],
            ['is_published' => true, 'display_category' => null],
        );

        $response = $this->getJson("/api/catalog/products/{$product->id}/related");

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_it_404s_for_unpublished_product(): void
    {
        $product = $this->makeProduct(
            ['accurate_id' => 20, 'sku' => 'C-1', 'name' => 'Draft Item'],
            ['is_published' => false, 'display_category' => 'Aksesoris'],
        );

        $this->getJson("/api/catalog/products/{$product->id}/related")->assertNotFound();
    }
}
