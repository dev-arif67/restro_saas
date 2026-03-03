@extends('emails.layout')

@section('body')
    <p>Hello {{ $user->name }},</p>

    <p>Welcome to <strong>{{ config('app.name') }}</strong>! Your account has been created successfully.</p>

    @if($tenantName)
        <div class="info-box">
            You have been added to <strong>{{ $tenantName }}</strong> as <strong>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</strong>.
        </div>
    @endif

    <table class="detail-table">
        <tr>
            <td>Name</td>
            <td>{{ $user->name }}</td>
        </tr>
        <tr>
            <td>Email</td>
            <td>{{ $user->email }}</td>
        </tr>
        <tr>
            <td>Role</td>
            <td>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</td>
        </tr>
    </table>

    <p style="text-align: center;">
        <a href="{{ config('app.frontend_url', config('app.url')) }}/login" class="btn">Login to Dashboard</a>
    </p>

    <p>If you have any questions, feel free to reach out to our support team.</p>
@endsection
