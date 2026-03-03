<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Subscription $subscription,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        $urgency = $this->daysRemaining <= 1 ? 'URGENT: ' : '';
        return new Envelope(
            subject: "{$urgency}Your subscription expires in {$this->daysRemaining} day(s) - " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiry-warning',
        );
    }
}
