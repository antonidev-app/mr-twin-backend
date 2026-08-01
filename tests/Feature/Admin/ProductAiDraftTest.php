<?php

namespace Tests\Feature\Admin;

use App\Models\SyncedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAiDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_parsed_draft_on_success(): void
    {
        $admin = User::factory()->create();
        $item = SyncedItem::create([
            'accurate_id' => 1,
            'sku' => 'LP-001',
            'name' => 'Laptop Contoh 14"',
            'item_type' => 'INVENTORY',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'display_name' => 'Laptop Contoh 14 inci',
                                    'description' => 'Laptop ringan untuk kerja sehari-hari.',
                                    'display_category' => 'Laptop & Komputer',
                                    'brand' => 'Contoh',
                                    'sources' => ['https://example.com/laptop-contoh'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$item->id}/ai-draft");

        $response->assertOk()->assertJson([
            'display_name' => 'Laptop Contoh 14 inci',
            'description' => 'Laptop ringan untuk kerja sehari-hari.',
            'display_category' => 'Laptop & Komputer',
            'brand' => 'Contoh',
            'sources' => ['https://example.com/laptop-contoh'],
        ]);
    }

    public function test_it_returns_502_when_openai_request_fails(): void
    {
        $admin = User::factory()->create();
        $item = SyncedItem::create([
            'accurate_id' => 2,
            'sku' => 'LP-002',
            'name' => 'Laptop Lain',
            'item_type' => 'INVENTORY',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$item->id}/ai-draft");

        $response->assertStatus(502)->assertJson(['message' => 'boom']);
    }
}
