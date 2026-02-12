<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderCreated;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseApiController
{
    /**
     * List orders (authenticated restaurant users)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items.menuItem', 'table', 'voucher']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->boolean('today')) {
            $query->today();
        }

        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to);
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        return $this->paginated($query->latest(), $request->get('per_page', 20));
    }

    /**
     * Create order (public - customer facing, no auth required)
     */
    public function store(StoreOrderRequest $request, string $tenantParam): JsonResponse
    {
        $tenant = is_numeric($tenantParam)
            ? Tenant::find($tenantParam)
            : Tenant::where('slug', $tenantParam)->first();

        if (!$tenant || !$tenant->is_active) {
            return $this->error('Restaurant not available', 404);
        }

        // Check subscription
        if ($tenant->isSubscriptionExpired()) {
            return $this->error('Restaurant is currently unavailable', 503);
        }

        // WiFi IP validation
        if ($tenant->isWifiEnforced()) {
            $clientIp = $request->ip();
            $authorizedIps = array_map('trim', explode(',', $tenant->authorized_wifi_ip));

            if (!in_array($clientIp, $authorizedIps)) {
                return $this->error('You must be connected to the restaurant WiFi to place an order', 403);
            }
        }

        return DB::transaction(function () use ($request, $tenant) {
            // Validate table for dine-in
            $tableId = null;
            if ($request->type === 'dine' && $request->table_id) {
                $table = RestaurantTable::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('id', $request->table_id)
                    ->first();

                if (!$table) {
                    return $this->error('Invalid table', 422);
                }

                $tableId = $table->id;
            }

            // Validate and process voucher
            $voucherId = null;
            $discount = 0;

            // Build order items and calculate subtotal
            $orderItems = [];
            $subtotal = 0;

            foreach ($request->items as $item) {
                $menuItem = MenuItem::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('id', $item['menu_item_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$menuItem) {
                    return $this->error("Menu item #{$item['menu_item_id']} is not available", 422);
                }

                $lineTotal = $menuItem->price * $item['qty'];
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'qty' => $item['qty'],
                    'price_at_sale' => $menuItem->price,
                    'line_total' => $lineTotal,
                    'special_instructions' => $item['special_instructions'] ?? null,
                ];
            }

            // Process voucher after subtotal calculation
            if ($request->voucher_code) {
                $voucher = Voucher::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('code', $request->voucher_code)
                    ->first();

                if ($voucher && $voucher->isValid()) {
                    $discount = $voucher->calculateDiscount($subtotal);
                    $voucherId = $voucher->id;
                    $voucher->incrementUsage();
                }
            }

            // Calculate tax
            $afterDiscount = $subtotal - $discount;
            $tax = round($afterDiscount * ($tenant->tax_rate / 100), 2);
            $grandTotal = $afterDiscount + $tax;

            // Create order
            $order = Order::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'table_id' => $tableId,
                'voucher_id' => $voucherId,
                'order_number' => Order::generateOrderNumber($tenant->id),
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'type' => $request->type,
                'status' => 'placed',
                'ip_address' => $request->ip(),
                'notes' => $request->notes,
                'payment_method' => $request->payment_method ?? 'cash',
                'payment_status' => ($request->payment_method === 'cash') ? 'pending' : 'pending',
            ]);

            // Create order items
            $order->items()->createMany($orderItems);

            // Mark table as occupied if dine-in
            if ($tableId) {
                RestaurantTable::withoutGlobalScopes()
                    ->where('id', $tableId)
                    ->update(['status' => 'occupied']);
            }

            $order->load(['items.menuItem', 'table', 'voucher']);

            // Broadcast new order event
            broadcast(new NewOrderCreated($order))->toOthers();

            return $this->created($order, 'Order placed successfully');
        });
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items.menuItem', 'table', 'voucher'])->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $oldStatus = $order->status;
        $newStatus = $request->status;

        $order->update(['status' => $newStatus]);

        // If completed and dine-in, free the table
        if (in_array($newStatus, ['completed', 'cancelled']) && $order->table_id) {
            $hasOtherActiveOrders = Order::where('table_id', $order->table_id)
                ->where('id', '!=', $order->id)
                ->active()
                ->exists();

            if (!$hasOtherActiveOrders) {
                RestaurantTable::where('id', $order->table_id)
                    ->update(['status' => 'available']);
            }
        }

        broadcast(new OrderStatusUpdated($order->fresh()))->toOthers();

        return $this->success($order->fresh()->load(['items.menuItem', 'table']), 'Order status updated');
    }

    public function cancel(int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->isCompleted()) {
            return $this->error('Cannot cancel a completed order', 422);
        }

        $order->update(['status' => 'cancelled']);

        // Free table if necessary
        if ($order->table_id) {
            $hasOtherActiveOrders = Order::where('table_id', $order->table_id)
                ->where('id', '!=', $order->id)
                ->active()
                ->exists();

            if (!$hasOtherActiveOrders) {
                RestaurantTable::where('id', $order->table_id)
                    ->update(['status' => 'available']);
            }
        }

        // Reverse voucher usage
        if ($order->voucher_id) {
            Voucher::where('id', $order->voucher_id)->decrement('used_count');
        }

        broadcast(new OrderStatusUpdated($order->fresh()))->toOthers();

        return $this->success($order->fresh(), 'Order cancelled');
    }

    /**
     * Public endpoint: Get order status by order number (no auth)
     */
    public function trackOrder(string $orderNumber): JsonResponse
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $orderNumber)
            ->with(['items.menuItem', 'table', 'voucher'])
            ->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->success($order);
    }

    /**
     * Public endpoint: Get full invoice data for an order
     */
    public function invoice(string $orderNumber): JsonResponse
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $orderNumber)
            ->with(['items.menuItem', 'table', 'voucher'])
            ->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        // Get tenant/restaurant info for the invoice header
        $tenant = Tenant::find($order->tenant_id);

        return $this->success([
            'order' => $order,
            'restaurant' => $tenant ? $tenant->only([
                'name', 'slug', 'logo', 'phone', 'address', 'email', 'currency', 'tax_rate',
            ]) : null,
        ]);
    }

    /**
     * Mark order payment as paid (restaurant staff/admin)
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => $request->transaction_id ?? null,
            'payment_gateway' => $request->payment_gateway ?? ($order->payment_method === 'cash' ? 'counter' : null),
        ]);

        return $this->success($order->fresh()->load(['items.menuItem', 'table', 'voucher']), 'Payment marked as paid');
    }
}
