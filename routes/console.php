<?php

use App\Jobs\CalculateSettlement;
use App\Jobs\CheckSubscriptionExpiry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check subscription expiry daily at midnight
Schedule::job(new CheckSubscriptionExpiry)->daily()->at('00:05');

// Calculate settlements monthly on the 1st
Schedule::job(new CalculateSettlement)->monthlyOn(1, '02:00');

// Clear expired cache
Schedule::command('cache:prune-stale-tags')->hourly();
