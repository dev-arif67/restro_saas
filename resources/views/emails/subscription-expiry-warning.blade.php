@extends('emails.layout')

@section('body')
    <p>Hello {{ $tenant->name }},</p>

    @if($daysRemaining <= 1)
        <div class="danger-box">
            <strong>Your subscription expires tomorrow!</strong> Your restaurant's online ordering system will stop working when the subscription expires.
        </div>
    @elseif($daysRemaining <= 3)
        <div class="danger-box">
            <strong>Your subscription expires in {{ $daysRemaining }} days.</strong> Please renew immediately to avoid service interruption.
        </div>
    @else
        <div class="alert-box">
            <strong>Your subscription expires in {{ $daysRemaining }} days.</strong> Please renew to continue uninterrupted service.
        </div>
    @endif

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
            <td>Expires On</td>
            <td>{{ $subscription->expires_at->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td>Days Remaining</td>
            <td><strong>{{ $daysRemaining }}</strong></td>
        </tr>
    </table>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url', config('app.url')) }}/dashboard/subscription" class="btn">Renew Subscription</a>
    </p>

    <p>If you have any questions, please contact our support team.</p>
@endsection
