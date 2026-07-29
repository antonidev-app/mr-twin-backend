<?php

namespace App\Jobs;

use App\Models\SyncedItem;
use App\Services\Accurate\AccurateClient;
use App\Services\Accurate\AccurateItemMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

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
        $count = 0;

        foreach ($client->paginate('item/list.do', AccurateItemMapper::fields()) as $page) {
            foreach ($page as $item) {
                SyncedItem::query()->updateOrCreate(
                    ['accurate_id' => $item['id']],
                    AccurateItemMapper::toSyncedItemAttributes($item),
                );
                $count++;
            }
        }

        Log::info('accurate.sync_items.completed', ['synced' => $count]);
    }
}
