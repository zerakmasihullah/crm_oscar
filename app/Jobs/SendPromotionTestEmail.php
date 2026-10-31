<?php

namespace App\Jobs;

use App\Mail\PromotionEmail;
use App\Models\Promotion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPromotionTestEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public Promotion $promotion,
        public string $email,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new PromotionEmail($this->promotion, $this->email)
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Promotion test email failed', [
            'promotion_id' => $this->promotion->id,
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
