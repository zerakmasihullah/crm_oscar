<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailLogService
{
    public function recordSent(MessageSent $event): EmailLog
    {
        /** @var Email $message */
        $message = $event->message;
        $to = $this->addresses($message->getTo());
        $primaryTo = $to[0]['address'] ?? null;

        return EmailLog::query()->create([
            'user_id' => $this->resolveUserId($primaryTo),
            'mailer' => config('mail.default'),
            'status' => EmailLog::STATUS_SENT,
            'subject' => $message->getSubject(),
            'from_address' => $this->addresses($message->getFrom())[0]['address'] ?? null,
            'from_name' => $this->addresses($message->getFrom())[0]['name'] ?? null,
            'to' => $to,
            'cc' => $this->addresses($message->getCc()),
            'bcc' => $this->addresses($message->getBcc()),
            'message_id' => $this->messageId($message, $event),
            'related_type' => $this->relatedType($event->data, $message),
            'body_preview' => $this->bodyPreview($message),
            'headers' => $this->interestingHeaders($message),
            'metadata' => [
                'attachment_count' => count($message->getAttachments()),
                'has_html' => filled($message->getHtmlBody()),
                'has_text' => filled($message->getTextBody()),
            ],
            'logged_at' => now(),
        ]);
    }

    public function recordFailure(
        string $subject,
        array $to,
        ?string $error = null,
        ?string $relatedType = null,
        array $metadata = [],
    ): EmailLog {
        $primaryTo = $to[0]['address'] ?? (is_string($to[0] ?? null) ? $to[0] : null);

        return EmailLog::query()->create([
            'user_id' => is_string($primaryTo) ? $this->resolveUserId($primaryTo) : null,
            'mailer' => config('mail.default'),
            'status' => EmailLog::STATUS_FAILED,
            'subject' => $subject,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'to' => $this->normalizeAddressList($to),
            'related_type' => $relatedType,
            'error' => $error,
            'metadata' => $metadata,
            'logged_at' => now(),
        ]);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array{address: string, name: string|null}>
     */
    protected function addresses(array $addresses): array
    {
        return array_values(array_map(
            fn (Address $address): array => [
                'address' => $address->getAddress(),
                'name' => $address->getName() !== '' ? $address->getName() : null,
            ],
            $addresses,
        ));
    }

    /**
     * @param  list<array{address?: string, name?: string|null}|string>  $addresses
     * @return list<array{address: string, name: string|null}>
     */
    protected function normalizeAddressList(array $addresses): array
    {
        $normalized = [];

        foreach ($addresses as $address) {
            if (is_string($address) && $address !== '') {
                $normalized[] = ['address' => $address, 'name' => null];

                continue;
            }

            if (is_array($address) && isset($address['address']) && is_string($address['address'])) {
                $normalized[] = [
                    'address' => $address['address'],
                    'name' => isset($address['name']) && is_string($address['name']) ? $address['name'] : null,
                ];
            }
        }

        return $normalized;
    }

    protected function resolveUserId(?string $email): ?int
    {
        if ($email === null || $email === '') {
            return null;
        }

        $userId = User::query()->where('email', $email)->value('id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    protected function messageId(Email $message, MessageSent $event): ?string
    {
        try {
            $sentMessageId = $event->sent->getMessageId();

            if (is_string($sentMessageId) && $sentMessageId !== '') {
                return $sentMessageId;
            }
        } catch (\Throwable) {
            // Fall through to header lookup.
        }

        $header = $message->getHeaders()->get('Message-ID');

        return $header?->getBodyAsString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function relatedType(array $data, Email $message): ?string
    {
        foreach (['__laravel_notification', 'notification', 'mailable'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_object($value)) {
                return $value::class;
            }
        }

        $header = $message->getHeaders()->get('X-Entity-Type')
            ?? $message->getHeaders()->get('X-Mail-Type')
            ?? $message->getHeaders()->get('X-Laravel-Notification');

        $value = $header?->getBodyAsString();

        return filled($value) ? $value : null;
    }

    protected function bodyPreview(Email $message): ?string
    {
        $text = $message->getTextBody();

        if (! filled($text)) {
            $html = $message->getHtmlBody();

            if (filled($html)) {
                $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if (! filled($text)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $text)) ?? '';

        return Str::limit($normalized, 500);
    }

    /**
     * @return array<string, string>
     */
    protected function interestingHeaders(Email $message): array
    {
        $headers = [];

        foreach (['Message-ID', 'X-Mailer', 'X-Entity-Type', 'X-Mail-Type', 'X-Laravel-Notification'] as $name) {
            $header = $message->getHeaders()->get($name);

            if ($header !== null) {
                $headers[$name] = $header->getBodyAsString();
            }
        }

        $attachments = [];

        foreach ($message->getAttachments() as $part) {
            $filename = method_exists($part, 'getFilename') ? $part->getFilename() : null;
            $attachments[] = is_string($filename) && $filename !== '' ? $filename : 'attachment';
        }

        if ($attachments !== []) {
            $headers['attachments'] = implode(', ', $attachments);
        }

        return $headers;
    }
}
