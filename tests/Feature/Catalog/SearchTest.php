<?php

namespace Tests\Feature\Catalog;

use App\Models\ProductDisplay;
use App\Models\SyncedItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $item = SyncedItem::create([
            'accurate_id' => 1,
            'sku' => 'LP-001',
            'name' => 'ASUS Vivobook 14',
        ]);

        ProductDisplay::create([
            'item_id' => $item->id,
            'is_published' => true,
            'display_category' => 'Laptop & Komputer',
        ]);

        // Real Accurate item names are long and spec-stuffed (see PLANNING.md
        // §7) — this guards against whole-string similarity() silently failing
        // on realistic data even when it passes on the short name above.
        $longItem = SyncedItem::create([
            'accurate_id' => 2,
            'sku' => 'CAM-001',
            'name' => '(NON)IPCAM CCTV HIKVISION DOME DS-2CD1121 (2MP CMOS/2.8MM LENS/ICR/4/H.264+/H.264/IP67/WDR/3D DNR/BLC)',
        ]);

        ProductDisplay::create([
            'item_id' => $longItem->id,
            'is_published' => true,
            'display_category' => 'Networking',
        ]);
    }

    public function test_it_finds_products_by_exact_substring(): void
    {
        $response = $this->getJson('/api/catalog/products?q=Vivobook');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_it_finds_products_by_a_typo(): void
    {
        $response = $this->getJson('/api/catalog/products?q=vivobok');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_it_finds_a_typo_inside_a_long_spec_stuffed_name(): void
    {
        $response = $this->getJson('/api/catalog/products?q=hikvison');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains(fn ($name) => str_contains($name, 'HIKVISION')));
    }

    public function test_it_does_not_match_unrelated_terms(): void
    {
        $response = $this->getJson('/api/catalog/products?q=kambing');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
