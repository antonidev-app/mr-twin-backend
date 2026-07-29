<?php

namespace App\Services\Accurate;

class AccurateItemMapper
{
    /**
     * Fields requested from item/list.do. Corrected against the real
     * response shape once accurate:sync-items --dry-run has been run
     * (see plan Open Question #4).
     */
    public static function fields(): array
    {
        return [
            'id', 'no', 'name', 'unitPrice', 'availableToSell',
            'itemType', 'itemCategory', 'suspended',
        ];
    }

    public static function toSyncedItemAttributes(array $item): array
    {
        return [
            'accurate_id' => $item['id'],
            'sku' => $item['no'] ?? null,
            'name' => $item['name'] ?? null,
            'unit_price' => $item['unitPrice'] ?? null,
            'stock' => $item['availableToSell'] ?? null,
            'item_type' => $item['itemType'] ?? null,
            'category_id' => self::extractCategoryId($item),
            'suspended' => (bool) ($item['suspended'] ?? false),
            'raw_json' => $item,
            'last_synced_at' => now(),
        ];
    }

    protected static function extractCategoryId(array $item): ?int
    {
        if (isset($item['itemCategoryId'])) {
            return (int) $item['itemCategoryId'];
        }

        if (isset($item['itemCategory']['id'])) {
            return (int) $item['itemCategory']['id'];
        }

        return null;
    }
}
