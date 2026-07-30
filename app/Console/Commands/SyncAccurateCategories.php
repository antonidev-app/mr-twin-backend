<?php

namespace App\Console\Commands;

use App\Jobs\SyncAccurateCategoriesJob;
use App\Services\Accurate\AccurateCategoryMapper;
use App\Services\Accurate\AccurateClient;
use Illuminate\Console\Command;

class SyncAccurateCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accurate:sync-categories {--sync : Run inline instead of dispatching to the queue} {--dry-run : Fetch and print the first page only, write nothing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync categories from Accurate item-category/list.do into the local synced_categories table';

    /**
     * Execute the console command.
     */
    public function handle(AccurateClient $client): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($client);
        }

        if ($this->option('sync')) {
            (new SyncAccurateCategoriesJob)->handle($client);
            $this->info('Sync completed inline.');

            return self::SUCCESS;
        }

        SyncAccurateCategoriesJob::dispatch();
        $this->info('Sync job dispatched to the queue.');

        return self::SUCCESS;
    }

    protected function dryRun(AccurateClient $client): int
    {
        foreach ($client->paginate('item-category/list.do', AccurateCategoryMapper::fields()) as $page) {
            $this->line('First page category count: '.count($page));
            $this->line(json_encode($page[0] ?? null, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->warn('No categories returned.');

        return self::SUCCESS;
    }
}
