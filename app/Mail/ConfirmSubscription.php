<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmSubscription extends Mailable
{
    use Queueable;

    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subscriber->locale === 'so'
            ? 'Xaqiiji isdiiwaangelintaada warsidaha Doogsan Media'
            : 'Confirm your Doogsan Media newsletter subscription');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirm-subscription', with: [
            'url' => lroute('newsletter.confirm', ['token' => $this->subscriber->token], $this->subscriber->locale),
            'locale' => $this->subscriber->locale,
        ]);
    }
}
