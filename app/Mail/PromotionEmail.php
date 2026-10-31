<?php

namespace App\Mail;

use App\Http\Controllers\MarketingTrackController;
use App\Http\Controllers\MarketingUnsubscribeController;
use App\Models\Promotion;
use App\Models\PromotionRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromotionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Promotion $promotion,
        public string $recipientEmail,
        public ?PromotionRecipient $recipient = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->promotion->subject,
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = MarketingUnsubscribeController::signedUrl($this->recipientEmail);
        $openPixelUrl = $this->recipient
            ? MarketingTrackController::openPixelUrl($this->recipient)
            : null;

        return new Content(
            view: 'emails.promotion',
            with: [
                'unsubscribeUrl' => $unsubscribeUrl,
                'openPixelUrl' => $openPixelUrl,
                'orderUrl' => rtrim((string) config('app.url'), '/').'/',
            ],
        );
    }

    public function attachments(): array
    {
        // Keep emails light for large campaigns — image is linked in HTML, not attached.
        return [];
    }
}
