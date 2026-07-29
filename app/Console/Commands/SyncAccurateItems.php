<?php

namespace App\Console\Commands;

use App\Jobs\SyncAccurateItemsJob;
use App\Services\Accurate\AccurateClient;
use App\Services\Accurate\AccurateItemMapper;
use Illuminate\Console\Command;

class SyncAccurateItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accurate:sync-items {--sync : Run inline instead of dispatching to the queue} {--dry-run : Fetch and print the first page only, write nothing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync items from Accurate item/list.do into the local synced_items table';

    /**
     * Execute the console command.
     */
    public function handle(AccurateClient $client): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($client);
        }

        if ($this->option('sync')) {
            (new SyncAccurateItemsJob)->handle($client);
            $this->info('Sync completed inline.');

            return self::SUCCESS;
        }

        SyncAccurateItemsJob::dispatch();
        $this->info('Sync job dispatched to the queue.');

        return self::SUCCESS;
    }

    protected function dryRun(AccurateClient $client): int
    {
        foreach ($client->paginate('item/list.do', AccurateItemMapper::fields()) as $page) {
            $this->line('First page item count: '.count($page));
            $this->line(json_encode($page[0] ?? null, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->warn('No items returned.');

        return self::SUCCESS;
    }
}
