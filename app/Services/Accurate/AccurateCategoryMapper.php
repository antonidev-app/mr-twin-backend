<?php

namespace App\Services\Accurate;

class AccurateCategoryMapper
{
    /**
     * Fields requested from item-category/list.do. Corrected against the
     * real response shape once accurate:sync-categories --dry-run has been
     * run, same as AccurateItemMapper::fields().
     */
    public static function fields(): array
    {
        return ['id', 'name', 'parent'];
    }

    public static function toSyncedCategoryAttributes(array $category): array
    {
        return [
            'accurate_id' => $category['id'],
            'name' => $category['name'] ?? null,
            'parent_id' => $category['parent']['id'] ?? null,
            'raw_json' => $category,
            'last_synced_at' => now(),
        ];
    }
}
