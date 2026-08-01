<?php

namespace App\Jobs;

use App\Models\SyncedCategory;
use App\Models\SyncLog;
use App\Services\Accurate\AccurateCategoryMapper;
use App\Services\Accurate\AccurateClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAccurateCategoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('accurate-sync-categories'))->releaseAfter(600)];
    }

    public function handle(AccurateClient $client): void
    {
        $log = SyncLog::create(['type' => 'categories', 'status' => 'running', 'started_at' => now()]);
        $count = 0;

        try {
            foreach ($client->paginate('item-category/list.do', AccurateCategoryMapper::fields()) as $page) {
                foreach ($page as $category) {
                    SyncedCategory::query()->updateOrCreate(
                        ['accurate_id' => $category['id']],
                        AccurateCategoryMapper::toSyncedCategoryAttributes($category),
                    );
                    $count++;
                }
            }

            $log->update(['status' => 'success', 'synced_count' => $count, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'synced_count' => $count,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        Log::info('accurate.sync_categories.completed', ['synced' => $count]);
    }
}
