<?php

use App\Jobs\SyncAccurateCategoriesJob;
use App\Jobs\SyncAccurateItemsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Categories first — items reference category_id, so keep synced_categories
// reasonably fresh before the (much larger, slower) item sync runs.
Schedule::job(new SyncAccurateCategoriesJob)->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new SyncAccurateItemsJob)->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
