<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Settlement;
use App\Models\SettlementPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Settlement::with('payments');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->latest());
    }

    public function show(int $id): JsonResponse
    {
        $settlement = Settlement::with('payments')->find($id);

        if (!$settlement) {
            return $this->notFound('Settlement not found');
        }

        return $this->success($settlement);
    }

    public function addPayment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'payment_ref' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $settlement = Settlement::find($id);

        if (!$settlement) {
            return $this->notFound('Settlement not found');
        }

        SettlementPayment::create([
            'settlement_id' => $settlement->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_ref' => $request->payment_ref,
            'paid_at' => now(),
            'notes' => $request->notes,
        ]);

        $settlement->recalculate();

        return $this->success($settlement->fresh()->load('payments'), 'Payment recorded');
    }

    /**
     * Super admin: List all settlements across tenants
     */
    public function allSettlements(Request $request): JsonResponse
    {
        $query = Settlement::withoutGlobalScopes()
            ->with(['tenant:id,name', 'payments']);

        if ($tenantId = $request->get('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->latest());
    }
}
