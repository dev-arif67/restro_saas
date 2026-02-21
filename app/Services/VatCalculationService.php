<?php

namespace App\Services;

use App\Models\Tenant;
use InvalidArgumentException;

class VatCalculationService
{
    /**
     * Calculate all financial totals for an order.
     *
     * @param  array  $items        Array of ['price' => decimal, 'qty' => int]
     * @param  Tenant $tenant       The restaurant tenant
     * @param  float  $discount     Discount amount (pre-validated, must not exceed subtotal)
     * @return array{subtotal: string, discount: string, net_amount: string, vat_rate: string, vat_amount: string, grand_total: string}
     */
    public function calculate(array $items, Tenant $tenant, float $discount = 0): array
    {
        $vatRate    = (float) $tenant->default_vat_rate;
        $vatInclusive = (bool) $tenant->vat_inclusive;

        return $this->computeTotals($items, $vatRate, $vatInclusive, $discount);
    }

    /**
     * Core calculation engine — stateless, testable.
     *
     * @param  array  $items        Array of ['price' => string|float, 'qty' => int]
     * @param  float  $vatRate      VAT rate as percentage (e.g. 5.00)
     * @param  bool   $vatInclusive Whether prices already include VAT
     * @param  float  $discount     Flat discount amount
     * @return array{subtotal: string, discount: string, net_amount: string, vat_rate: string, vat_amount: string, grand_total: string}
     */
    public function computeTotals(array $items, float $vatRate, bool $vatInclusive, float $discount = 0): array
    {
        // Step 1: Subtotal = sum of (price * qty) using bcmath for precision
        $subtotal = '0.00';
        foreach ($items as $item) {
            $lineTotal = bcmul((string) $item['price'], (string) $item['qty'], 2);
            $subtotal  = bcadd($subtotal, $lineTotal, 2);
        }

        // Validate discount
        $discount = (string) round($discount, 2);
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw new InvalidArgumentException('Discount cannot exceed subtotal.');
        }

        if (bccomp($discount, '0.00', 2) < 0) {
            throw new InvalidArgumentException('Discount cannot be negative.');
        }

        // Step 2: Net amount = subtotal - discount
        $netAmount = bcsub($subtotal, $discount, 2);

        // Step 3: VAT calculation
        $vatRateStr = (string) round($vatRate, 2);

        if ($vatInclusive) {
            // VAT is already included in prices
            // vat = net_amount × (vat_rate / (100 + vat_rate))
            $divisor   = bcadd('100', $vatRateStr, 2);
            $vatAmount = bcdiv(bcmul($netAmount, $vatRateStr, 4), $divisor, 2);

            // Actual net (excluding VAT) = net_amount - vat
            $actualNet = bcsub($netAmount, $vatAmount, 2);

            // Grand total = original net_amount (prices already include VAT)
            $grandTotal = $netAmount;

            // Update net_amount to the VAT-exclusive portion
            $netAmount = $actualNet;
        } else {
            // VAT exclusive — add VAT on top
            // vat = net_amount × (vat_rate / 100)
            $vatAmount  = bcdiv(bcmul($netAmount, $vatRateStr, 4), '100', 2);
            $grandTotal = bcadd($netAmount, $vatAmount, 2);
        }

        return [
            'subtotal'    => $subtotal,
            'discount'    => $discount,
            'net_amount'  => $netAmount,
            'vat_rate'    => $vatRateStr,
            'vat_amount'  => $vatAmount,
            'grand_total' => $grandTotal,
        ];
    }
}
