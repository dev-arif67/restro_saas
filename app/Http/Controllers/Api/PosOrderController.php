<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderCreated;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosOrderController extends BaseApiController
{
    /**
     * Create an order from the POS terminal (authenticated staff/admin).
     *
     * Differences from the public CustomerController flow:
     *  - No WiFi IP check (staff are trusted / on-premises)
     *  - Tenant is resolved from the authenticated user's tenant_id
     *  - Supports order types: dine | parcel | quick
     *  - Supports payment methods: cash | card | mobile_banking
     *  - Can mark payment as paid immediately (payment_status = 'paid')
     *  - Records served_by = authenticated user id
     *  - Sets source = 'pos'
     */
    public function store(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $tenant = $user->tenant;

        if (!$tenant || !$tenant->is_active) {
            return $this->error('Restaurant account is not active', 403);
        }

        if ($tenant->isSubscriptionExpired()) {
            return $this->error('Subscription has expired', 402);
        }

        // ── Validation ─────────────────────────────────────────────────
        $validated = $request->validate([
            'type'             => ['required', 'in:dine,parcel,quick'],
            'table_id'         => ['nullable', 'integer'],
            'customer_name'    => ['nullable', 'string', 'max:100'],
            'customer_phone'   => ['nullable', 'string', 'max:20'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.menu_item_id'          => ['required', 'integer'],
            'items.*.qty'                   => ['required', 'integer', 'min:1'],
            'items.*.special_instructions'  => ['nullable', 'string', 'max:255'],
            'voucher_code'     => ['nullable', 'string'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'payment_method'   => ['required', 'in:cash,card,mobile_banking'],
            'payment_status'   => ['required', 'in:paid,pending'],
            'transaction_id'   => ['nullable', 'string', 'max:100'],
        ]);

        // Extra validation: parcel needs customer name
        if ($validated['type'] === 'parcel' && empty($validated['customer_name'])) {
            return $this->error('Customer name is required for takeaway orders', 422);
        }

        return DB::transaction(function () use ($validated, $user, $tenant) {

            // ── Table validation (dine-in only) ─────────────────────────
            $tableId = null;
            if ($validated['type'] === 'dine' && !empty($validated['table_id'])) {
                $table = RestaurantTable::where('tenant_id', $tenant->id)
                    ->where('id', $validated['table_id'])
                    ->first();

                if (!$table) {
                    return $this->error('Invalid table selected', 422);
                }

                $tableId = $table->id;
            }

            // ── Build order items & calculate subtotal ──────────────────
            $orderItems = [];
            $subtotal   = 0;

            foreach ($validated['items'] as $item) {
                $menuItem = MenuItem::where('tenant_id', $tenant->id)
                    ->where('id', $item['menu_item_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$menuItem) {
                    return $this->error("Menu item #{$item['menu_item_id']} is unavailable", 422);
                }

                $lineTotal  = $menuItem->price * $item['qty'];
                $subtotal  += $lineTotal;

                $orderItems[] = [
                    'menu_item_id'          => $menuItem->id,
                    'qty'                   => $item['qty'],
                    'price_at_sale'         => $menuItem->price,
                    'line_total'            => $lineTotal,
                    'special_instructions'  => $item['special_instructions'] ?? null,
                ];
            }

            // ── Voucher ─────────────────────────────────────────────────
            $voucherId = null;
            $discount  = 0;

            if (!empty($validated['voucher_code'])) {
                $voucher = Voucher::where('tenant_id', $tenant->id)
                    ->where('code', $validated['voucher_code'])
                    ->first();

                if ($voucher && $voucher->isValid()) {
                    $discount  = $voucher->calculateDiscount($subtotal);
                    $voucherId = $voucher->id;
                    $voucher->incrementUsage();
                }
            }

            // ── Totals ──────────────────────────────────────────────────
            $afterDiscount = $subtotal - $discount;
            $tax           = round($afterDiscount * ($tenant->tax_rate / 100), 2);
            $grandTotal    = $afterDiscount + $tax;

            // ── Create order ────────────────────────────────────────────
            $isPaid = $validated['payment_status'] === 'paid';

            $order = Order::create([
                'tenant_id'       => $tenant->id,
                'table_id'        => $tableId,
                'voucher_id'      => $voucherId,
                'order_number'    => Order::generateOrderNumber($tenant->id),
                'customer_name'   => $validated['customer_name'] ?? null,
                'customer_phone'  => $validated['customer_phone'] ?? null,
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'tax'             => $tax,
                'grand_total'     => $grandTotal,
                'type'            => $validated['type'],
                'status'          => 'placed',
                'ip_address'      => request()->ip(),
                'notes'           => $validated['notes'] ?? null,
                'payment_method'  => $validated['payment_method'],
                'payment_status'  => $isPaid ? 'paid' : 'pending',
                'transaction_id'  => $validated['transaction_id'] ?? null,
                'paid_at'         => $isPaid ? now() : null,
                'source'          => 'pos',
                'served_by'       => $user->id,
            ]);

            // ── Order items ─────────────────────────────────────────────
            $order->items()->createMany($orderItems);

            // ── Mark table occupied ─────────────────────────────────────
            if ($tableId) {
                RestaurantTable::where('id', $tableId)
                    ->update(['status' => 'occupied']);
            }

            $order->load(['items.menuItem', 'table', 'voucher']);

            broadcast(new NewOrderCreated($order))->toOthers();

            return $this->created($order, 'POS order created successfully');
        });
    }
}
