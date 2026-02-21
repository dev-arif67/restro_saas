<?php

namespace App\Services;

use App\Models\InvoiceCounter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceNumberService
{
    /**
     * Generate the next sequential invoice number for a restaurant.
     *
     * MUST be called inside an active DB transaction.
     * Uses SELECT ... FOR UPDATE to prevent race conditions.
     *
     * Format: INV-{TENANT_ID}-{YYYYMM}-{SEQUENCE}
     * Example: INV-12-202602-000042
     *
     * @param  int $tenantId
     * @return string
     *
     * @throws RuntimeException if not inside a transaction
     */
    public function generate(int $tenantId): string
    {
        // Ensure we are inside a transaction for safety
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('InvoiceNumberService::generate() must be called within a DB transaction.');
        }

        // Atomic row-level lock: get or create the counter row
        $counter = InvoiceCounter::where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first();

        if (!$counter) {
            $counter = InvoiceCounter::create([
                'tenant_id'           => $tenantId,
                'last_invoice_number' => 0,
            ]);

            // Re-select with lock after creation
            $counter = InvoiceCounter::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();
        }

        $nextNumber = $counter->last_invoice_number + 1;

        $counter->update(['last_invoice_number' => $nextNumber]);

        $yearMonth = now()->format('Ym');

        return sprintf('INV-%d-%s-%06d', $tenantId, $yearMonth, $nextNumber);
    }
}
