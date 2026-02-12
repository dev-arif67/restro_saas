<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private channel for tenant orders (restaurant dashboard + kitchen)
Broadcast::channel('tenant.{tenantId}.orders', function ($user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Public channel for menu availability (customer facing)
Broadcast::channel('tenant.{tenantId}.menu', function () {
    return true; // Public channel, any customer can listen
});

// Public channel for order tracking
Broadcast::channel('order.{orderNumber}', function () {
    return true; // Public - customers track their order
});
