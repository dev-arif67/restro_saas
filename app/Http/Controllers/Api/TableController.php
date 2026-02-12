<?php

namespace App\Http\Controllers\Api;

use App\Events\TableTransferred;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\TransferTableRequest;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TableController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = RestaurantTable::withCount('activeOrders');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return $this->success($query->orderBy('table_number')->get());
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        // Check unique table number within tenant
        $exists = RestaurantTable::where('tenant_id', $tenantId)
            ->where('table_number', $request->table_number)
            ->exists();

        if ($exists) {
            return $this->error('Table number already exists', 422);
        }

        $table = RestaurantTable::create([
            'table_number' => $request->table_number,
            'capacity' => $request->capacity ?? 4,
            'status' => $request->status ?? 'available',
            'qr_code' => $this->generateQrIdentifier($tenantId, $request->table_number),
        ]);

        return $this->created($table, 'Table created');
    }

    public function show(int $id): JsonResponse
    {
        $table = RestaurantTable::with(['activeOrders.items.menuItem'])->find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        return $this->success($table);
    }

    public function update(StoreTableRequest $request, int $id): JsonResponse
    {
        $table = RestaurantTable::find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        $table->update($request->validated());

        return $this->success($table->fresh(), 'Table updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $table = RestaurantTable::find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        if ($table->activeOrders()->exists()) {
            return $this->error('Cannot delete table with active orders', 422);
        }

        $table->delete();

        return $this->success(null, 'Table deleted');
    }

    public function transfer(TransferTableRequest $request): JsonResponse
    {
        $fromTable = RestaurantTable::find($request->from_table_id);
        $toTable = RestaurantTable::find($request->to_table_id);

        if (!$fromTable || !$toTable) {
            return $this->notFound('Table not found');
        }

        if (!$fromTable->activeOrders()->exists()) {
            return $this->error('No active orders on source table', 422);
        }

        if ($toTable->activeOrders()->exists()) {
            return $this->error('Destination table already has active orders', 422);
        }

        // Transfer all active orders
        $fromTable->activeOrders()->update(['table_id' => $toTable->id]);

        $fromTable->markAvailable();
        $toTable->markOccupied();

        broadcast(new TableTransferred($fromTable, $toTable))->toOthers();

        return $this->success([
            'from_table' => $fromTable->fresh(),
            'to_table' => $toTable->fresh()->load('activeOrders'),
        ], 'Table transferred successfully');
    }

    public function generateQrCode(int $id): JsonResponse
    {
        $table = RestaurantTable::find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        $qrIdentifier = $this->generateQrIdentifier($table->tenant_id, $table->table_number);
        $table->update(['qr_code' => $qrIdentifier]);

        $tenant = Tenant::find($table->tenant_id);
        $slug = $tenant ? $tenant->slug : $table->tenant_id;
        $qrUrl = config('app.frontend_url') . "/restaurant/{$slug}?table={$table->id}&qr={$qrIdentifier}";

        return $this->success([
            'table' => $table,
            'qr_url' => $qrUrl,
            'qr_identifier' => $qrIdentifier,
        ]);
    }

    public function generateParcelQr(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $tenant = Tenant::find($tenantId);
        $slug = $tenant ? $tenant->slug : $tenantId;
        $qrIdentifier = 'PARCEL-' . Str::upper(Str::random(8));

        $qrUrl = config('app.frontend_url') . "/restaurant/{$slug}?type=parcel&qr={$qrIdentifier}";

        return $this->success([
            'qr_url' => $qrUrl,
            'qr_identifier' => $qrIdentifier,
        ]);
    }

    private function generateQrIdentifier(int $tenantId, string $tableNumber): string
    {
        return 'TBL-' . $tenantId . '-' . $tableNumber . '-' . Str::upper(Str::random(6));
    }
}
