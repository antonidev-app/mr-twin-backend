<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiEnrichmentException;
use App\Models\SyncedCategory;
use App\Models\SyncedItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiClient
{
    /**
     * Kept in sync with the storefront categories listed in PLANNING.md §4 —
     * constrains the model instead of letting it invent new categories.
     */
    protected const CATEGORIES = [
        'Laptop & Komputer',
        'Printer & Scanner',
        'Audio',
        'Aksesoris',
        'Networking',
    ];

    public function __construct(
        protected string $apiKey,
        protected string $model,
    ) {}

    public function draftProduct(SyncedItem $item): array
    {
        $categoryName = $item->category_id
            ? SyncedCategory::find($item->category_id)?->name
            : null;

        $response = Http::timeout(45)
            ->withToken($this->apiKey)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $this->model,
                'instructions' => $this->instructions(),
                'input' => $this->prompt($item, $categoryName),
                'tools' => [['type' => 'web_search']],
                'text' => ['format' => $this->responseFormat()],
                // Descriptions can now include a spec list on top of reasoning +
                // web search tokens — leave enough headroom to avoid the JSON
                // output getting cut off mid-structure.
                'max_output_tokens' => 4096,
            ]);

        $json = $response->json() ?? [];

        if (isset($json['error'])) {
            Log::error('openai.enrichment.failed', ['item_id' => $item->id, 'error' => $json['error']]);
            throw new AiEnrichmentException($json['error']['message'] ?? 'OpenAI request failed');
        }

        if (! $response->successful()) {
            Log::error('openai.enrichment.failed', [
                'item_id' => $item->id,
                'status' => $response->status(),
                'body' => $json,
            ]);
            throw new AiEnrichmentException("OpenAI request failed with status {$response->status()}");
        }

        $draft = $this->extractDraft($json);

        if ($draft === null) {
            Log::error('openai.enrichment.invalid_response', ['item_id' => $item->id, 'response' => $json]);
            throw new AiEnrichmentException('OpenAI returned an unparsable response');
        }

        return $draft;
    }

    protected function instructions(): string
    {
        return 'Kamu adalah copywriter katalog untuk Mr. Twin, marketplace reseller IT di Indonesia. '
            .'Kamu akan diberi data mentah satu produk dari ERP (Accurate). Riset produk ini di internet '
            .'(cari berdasarkan nama/SKU/brand) untuk memastikan nama dan spesifikasinya akurat, lalu susun '
            .'draft tampilan produk untuk katalog.'
            ."\n\n"
            .'Aturan untuk field `description` (Bahasa Indonesia):'
            ."\n"
            .'- Selalu mulai dengan 1 paragraf pembuka pendek (2-3 kalimat): apa produknya dan manfaat '
            .'utamanya untuk pembeli.'
            ."\n"
            .'- Kalau hasil riset kamu memadai (sumber cukup detail/konsisten soal spesifikasi produk), '
            .'lanjutkan dengan daftar spesifikasi dalam bentuk list — baris baru (\n), tiap baris diawali '
            .'"- ", format "- Label: nilai" (mis. "- Panjang: 100 meter", "- Material konduktor: '
            .'tembaga"). Sertakan sebanyak mungkin spek yang benar-benar kamu temukan dan yakini benar; '
            .'jangan isi list dengan spekulasi.'
            ."\n"
            .'- Kalau relevan, tutup dengan 1 kalimat singkat yang menonjolkan nilai jual (use case umum, '
            .'kenapa produk ini worth dibeli, target pemakaian) — tetap berdasar fakta yang kamu tahu, '
            .'bukan klaim marketing yang tidak berdasar (jangan klaim "terbaik", "termurah", bergaransi, '
            .'dll kecuali benar-benar kamu temukan sebagai fakta).'
            ."\n"
            .'- Kalau hasil riset tipis/tidak memadai, cukup tulis paragraf pembuka saja tanpa list — '
            .'JANGAN memaksakan list dengan spekulasi hanya supaya deskripsinya kelihatan panjang.'
            ."\n"
            .'- Kalau ada detail yang tidak kamu temukan atau tidak yakin, JANGAN disebutkan sama sekali '
            .'(jangan tulis "tidak dapat diverifikasi", "cek label produk", "belum bisa dipastikan", atau '
            .'kalimat sejenis) — cukup fokus ke apa yang sudah kamu ketahui dengan yakin.'
            ."\n"
            .'- Jangan menyebutkan proses risetmu, sumbernya, atau AI/model itu sendiri di dalam teks '
            .'description — itu tugas field `sources` yang terpisah, bukan bagian dari copy yang dibaca '
            .'pembeli.'
            ."\n"
            .'- Jangan mengarang spesifikasi yang sama sekali tidak berdasar dari hasil riset.';
    }

    protected function prompt(SyncedItem $item, ?string $categoryName): string
    {
        return "Data produk dari ERP:\n"
            ."- Nama: {$item->name}\n"
            ."- SKU: {$item->sku}\n"
            ."- Tipe item: {$item->item_type}\n"
            .'- Kategori Accurate: '.($categoryName ?? '(tidak ada)');
    }

    protected function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'product_draft',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'display_name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'display_category' => ['type' => 'string', 'enum' => self::CATEGORIES],
                    'brand' => ['type' => 'string'],
                    'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['display_name', 'description', 'display_category', 'brand', 'sources'],
                'additionalProperties' => false,
            ],
        ];
    }

    protected function extractDraft(array $json): ?array
    {
        foreach ($json['output'] ?? [] as $outputItem) {
            if (($outputItem['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($outputItem['content'] ?? [] as $content) {
                if (($content['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $decoded = json_decode($content['text'], true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }
        }

        return null;
    }
}
