<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAccurateCategoriesJob;
use App\Jobs\SyncAccurateItemsJob;
use App\Models\SyncedCategory;
use App\Models\SyncedItem;
use App\Models\SyncLog;

class SyncController extends Controller
{
    public function syncItems()
    {
        SyncAccurateItemsJob::dispatch();

        return response()->json(['message' => 'Sync item dijadwalkan.']);
    }

    public function syncCategories()
    {
        SyncAccurateCategoriesJob::dispatch();

        return response()->json(['message' => 'Sync kategori dijadwalkan.']);
    }

    public function logs()
    {
        return response()->json(SyncLog::query()->latest('started_at')->paginate(20));
    }

    public function status()
    {
        return response()->json([
            'items' => [
                'total' => SyncedItem::count(),
                'last_log' => SyncLog::where('type', 'items')->latest('started_at')->first(),
            ],
            'categories' => [
                'total' => SyncedCategory::count(),
                'last_log' => SyncLog::where('type', 'categories')->latest('started_at')->first(),
            ],
        ]);
    }
}
