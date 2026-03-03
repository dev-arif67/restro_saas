@extends('emails.layout')

@section('body')
    <p>Hello {{ $order->customer_name ?? 'Customer' }},</p>

    <p>Thank you for your order at <strong>{{ $restaurantName }}</strong>! Your order has been confirmed.</p>

    <div class="info-box">
        <strong>Order Number:</strong> {{ $order->order_number }}<br>
        <strong>Type:</strong> {{ $order->type === 'dine' ? 'Dine-in' : 'Parcel/Takeaway' }}
    </div>

    <table class="detail-table">
        <tr>
            <td>Order Number</td>
            <td><strong>{{ $order->order_number }}</strong></td>
        </tr>
        @if($order->invoice_number)
        <tr>
            <td>Invoice</td>
            <td>{{ $order->invoice_number }}</td>
        </tr>
        @endif
        <tr>
            <td>Type</td>
            <td>{{ $order->type === 'dine' ? 'Dine-in' : 'Parcel' }}</td>
        </tr>
        <tr>
            <td>Subtotal</td>
            <td>৳{{ number_format($order->subtotal, 2) }}</td>
        </tr>
        @if($order->discount > 0)
        <tr>
            <td>Discount</td>
            <td>-৳{{ number_format($order->discount, 2) }}</td>
        </tr>
        @endif
        @if($order->vat_amount > 0)
        <tr>
            <td>VAT ({{ $order->vat_rate }}%)</td>
            <td>৳{{ number_format($order->vat_amount, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td><strong>Total</strong></td>
            <td><strong>৳{{ number_format($order->grand_total, 2) }}</strong></td>
        </tr>
    </table>

    <p style="text-align: center;">
        <a href="{{ $trackingUrl }}" class="btn">Track Your Order</a>
    </p>

    <p>Thank you for choosing {{ $restaurantName }}!</p>
@endsection
