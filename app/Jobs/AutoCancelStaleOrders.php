<?php

namespace App\Jobs;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoCancelStaleOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $autoCancelMinutes = config('saas.order.auto_cancel_minutes', 30);

        Log::info("Running auto-cancel for orders older than {$autoCancelMinutes} minutes in 'placed' status...");

        $staleOrders = Order::withoutGlobalScopes()
            ->where('status', 'placed')
            ->where('created_at', '<', now()->subMinutes($autoCancelMinutes))
            ->get();

        $cancelledCount = 0;

        foreach ($staleOrders as $order) {
            $order->update([
                'status' => 'cancelled',
                'notes' => trim(($order->notes ?? '') . "\n[Auto-cancelled: No confirmation after {$autoCancelMinutes} minutes]"),
            ]);

            // Free table if dine-in
            if ($order->table_id) {
                $hasOtherActiveOrders = Order::withoutGlobalScopes()
                    ->where('table_id', $order->table_id)
                    ->where('id', '!=', $order->id)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->exists();

                if (!$hasOtherActiveOrders) {
                    RestaurantTable::where('id', $order->table_id)
                        ->update(['status' => 'available']);
                }
            }

            // Restore voucher usage if applicable
            if ($order->voucher_id && $order->voucher) {
                $order->voucher->decrement('used_count');
            }

            // Broadcast the cancellation
            try {
                broadcast(new OrderStatusUpdated($order->fresh()));
            } catch (\Exception $e) {
                Log::warning("Failed to broadcast auto-cancel for order {$order->order_number}: " . $e->getMessage());
            }

            $cancelledCount++;

            Log::info("Auto-cancelled order {$order->order_number} (Tenant: {$order->tenant_id})");
        }

        Log::info("Auto-cancel complete. Cancelled {$cancelledCount} stale orders.");
    }
}
