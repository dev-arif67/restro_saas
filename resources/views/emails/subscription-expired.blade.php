@extends('emails.layout')

@section('body')
    <p>Hello {{ $tenant->name }},</p>

    <div class="danger-box">
        <strong>Your subscription has expired.</strong> Your restaurant's online ordering system is now inactive. Customers and staff can no longer access your services.
    </div>

    <table class="detail-table">
        <tr>
            <td>Restaurant</td>
            <td>{{ $tenant->name }}</td>
        </tr>
        <tr>
            <td>Plan</td>
            <td>{{ ucfirst($subscription->plan_type) }}</td>
        </tr>
        <tr>
            <td>Expired On</td>
            <td>{{ $subscription->expires_at->format('F j, Y') }}</td>
        </tr>
    </table>

    <p>To restore your services, please renew your subscription immediately:</p>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url', config('app.url')) }}/dashboard/subscription" class="btn">Renew Now</a>
    </p>

    <p>If you need assistance, contact our support team or reply to this email.</p>
@endsection
