<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class VatReportService
{
    /**
     * Daily Z Report — per restaurant per day.
     *
     * Uses STORED values only. Never recalculates VAT.
     *
     * @param  int    $tenantId
     * @param  string $date  Y-m-d format
     * @return array
     */
    public function dailyZReport(int $tenantId, string $date): array
    {
        $orders = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->where('status', '!=', 'cancelled');

        $summary = (clone $orders)->select([
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(subtotal), 0) as total_subtotal'),
            DB::raw('COALESCE(SUM(discount), 0) as total_discount'),
            DB::raw('COALESCE(SUM(net_amount), 0) as total_net_amount'),
            DB::raw('COALESCE(SUM(vat_amount), 0) as total_vat_collected'),
            DB::raw('COALESCE(SUM(grand_total), 0) as total_sales'),
        ])->first();

        $byPaymentMethod = (clone $orders)->select([
            'payment_method',
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(grand_total), 0) as total_amount'),
            DB::raw('COALESCE(SUM(vat_amount), 0) as vat_amount'),
        ])
            ->groupBy('payment_method')
            ->get();

        $byOrderType = (clone $orders)->select([
            'type',
            DB::raw('COUNT(*) as order_count'),
            DB::raw('COALESCE(SUM(grand_total), 0) as total_amount'),
        ])
            ->groupBy('type')
            ->get();

        return [
            'date'       => $date,
            'tenant_id'  => $tenantId,
            'summary'    => [
                'order_count'        => (int) $summary->order_count,
                'total_subtotal'     => $summary->total_subtotal,
                'total_discount'     => $summary->total_discount,
                'total_net_amount'   => $summary->total_net_amount,
                'total_vat_collected' => $summary->total_vat_collected,
                'total_sales'        => $summary->total_sales,
            ],
            'by_payment_method' => $byPaymentMethod,
            'by_order_type'     => $byOrderType,
        ];
    }

    /**
     * Monthly VAT Report — for date range.
     *
     * Uses STORED values only. Never recalculates VAT.
     *
     * @param  int    $tenantId
     * @param  string $from  Y-m-d
     * @param  string $to    Y-m-d
     * @return array
     */
    public function monthlyVatReport(int $tenantId, string $from, string $to): array
    {
        $orders = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('status', '!=', 'cancelled');

        $summary = (clone $orders)->select([
            DB::raw('COUNT(*) as total_invoices'),
            DB::raw('COALESCE(SUM(subtotal), 0) as total_subtotal'),
            DB::raw('COALESCE(SUM(discount), 0) as total_discount'),
            DB::raw('COALESCE(SUM(net_amount), 0) as total_taxable_sales'),
            DB::raw('COALESCE(SUM(vat_amount), 0) as total_vat_collected'),
            DB::raw('COALESCE(SUM(grand_total), 0) as total_sales'),
        ])->first();

        // Daily breakdown using stored values
        $dailyBreakdown = (clone $orders)->select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as invoice_count'),
            DB::raw('COALESCE(SUM(net_amount), 0) as taxable_sales'),
            DB::raw('COALESCE(SUM(vat_amount), 0) as vat_collected'),
            DB::raw('COALESCE(SUM(grand_total), 0) as total_sales'),
            DB::raw('COALESCE(SUM(discount), 0) as discounts'),
        ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // VAT rate breakdown (in case rate changed over time)
        $byVatRate = (clone $orders)->select([
            'vat_rate',
            DB::raw('COUNT(*) as invoice_count'),
            DB::raw('COALESCE(SUM(net_amount), 0) as taxable_sales'),
            DB::raw('COALESCE(SUM(vat_amount), 0) as vat_collected'),
        ])
            ->groupBy('vat_rate')
            ->get();

        return [
            'period'    => ['from' => $from, 'to' => $to],
            'tenant_id' => $tenantId,
            'summary'   => [
                'total_invoices'      => (int) $summary->total_invoices,
                'total_subtotal'      => $summary->total_subtotal,
                'total_discount'      => $summary->total_discount,
                'total_taxable_sales' => $summary->total_taxable_sales,
                'total_vat_collected' => $summary->total_vat_collected,
                'total_sales'         => $summary->total_sales,
            ],
            'daily_breakdown'  => $dailyBreakdown,
            'by_vat_rate'      => $byVatRate,
        ];
    }
}
