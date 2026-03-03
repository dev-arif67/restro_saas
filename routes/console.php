<?php

use App\Jobs\AutoCancelStaleOrders;
use App\Jobs\CalculateSettlement;
use App\Jobs\CheckSubscriptionExpiry;
use App\Jobs\SendSubscriptionExpiryWarnings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check subscription expiry daily at midnight
Schedule::job(new CheckSubscriptionExpiry)->daily()->at('00:05');

// Send subscription expiry warnings daily at 9 AM
Schedule::job(new SendSubscriptionExpiryWarnings)->daily()->at('09:00');

// Auto-cancel stale orders every 5 minutes
Schedule::job(new AutoCancelStaleOrders)->everyFiveMinutes();

// Calculate settlements monthly on the 1st
Schedule::job(new CalculateSettlement)->monthlyOn(1, '02:00');

// Clear expired cache
Schedule::command('cache:prune-stale-tags')->hourly();
