<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterIssue extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public NewsletterCampaign $campaign, public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter', with: [
            'campaign' => $this->campaign,
            'stories' => $this->campaign->articles(),
            'unsubscribe' => lroute('newsletter.unsubscribe', ['token' => $this->subscriber->token], $this->subscriber->locale),
            'locale' => $this->campaign->locale,
        ]);
    }
}
