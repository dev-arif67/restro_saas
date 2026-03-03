@extends('emails.layout')

@section('body')
    <p>Hello {{ $userName }},</p>

    <p>We received a request to reset your password. Click the button below to set a new password:</p>

    <p style="text-align: center;">
        <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
    </p>

    <p>If the button doesn't work, copy and paste this link into your browser:</p>
    <p style="word-break: break-all; font-size: 13px; color: #666;">{{ $resetUrl }}</p>

    <div class="alert-box">
        <strong>This link will expire in 60 minutes.</strong> If you did not request a password reset, you can safely ignore this email.
    </div>

    <p>For security, your reset token is: <code>{{ $token }}</code></p>
@endsection
