<?php

namespace App\Jobs;

use App\Models\SyncedItem;
use App\Models\SyncLog;
use App\Services\Accurate\AccurateClient;
use App\Services\Accurate\AccurateItemMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAccurateItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('accurate-sync-items'))->releaseAfter(1800)];
    }

    public function handle(AccurateClient $client): void
    {
        $log = SyncLog::create(['type' => 'items', 'status' => 'running', 'started_at' => now()]);
        $count = 0;

        try {
            foreach ($client->paginate('item/list.do', AccurateItemMapper::fields()) as $page) {
                foreach ($page as $item) {
                    SyncedItem::query()->updateOrCreate(
                        ['accurate_id' => $item['id']],
                        AccurateItemMapper::toSyncedItemAttributes($item),
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

        Log::info('accurate.sync_items.completed', ['synced' => $count]);
    }
}
