<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

class AiChatAttachmentService
{
    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'text/plain',
            'text/csv',
            'application/csv',
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{name: string, path: string, mime: string, size: int, attachment: File}>
     */
    public function storeForUser(int $userId, array $files): array
    {
        $maxFiles = (int) config('ai.chat_attachments.max_files', 5);
        $maxKb = (int) config('ai.chat_attachments.max_kb', 10_240);

        if (count($files) > $maxFiles) {
            throw new InvalidArgumentException("You can attach at most {$maxFiles} files per message.");
        }

        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (! $file->isValid()) {
                throw new InvalidArgumentException('One of the uploads is invalid.');
            }

            if ($file->getSize() > $maxKb * 1024) {
                throw new InvalidArgumentException(
                    "{$file->getClientOriginalName()} is too large (max {$maxKb} KB).",
                );
            }

            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
            if (! in_array($mime, self::allowedMimes(), true)) {
                throw new InvalidArgumentException(
                    "{$file->getClientOriginalName()} type is not allowed. Use PDF, images, TXT, or CSV.",
                );
            }

            $this->assertMagicMime($file, $mime);
            $this->assertAttachmentContentSafe($file, $mime);

            $dir = 'ai-chat/'.$userId.'/'.now()->format('Y/m/d');
            $name = Str::uuid()->toString().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext = match (true) {
                str_starts_with($mime, 'image/jpeg') => 'jpg',
                $mime === 'image/png' => 'png',
                $mime === 'image/webp' => 'webp',
                $mime === 'image/gif' => 'gif',
                $mime === 'application/pdf' => 'pdf',
                $mime === 'text/csv', $mime === 'application/csv' => 'csv',
                default => 'txt',
            };
            $path = $file->storeAs($dir, $name.'.'.$ext, 'local');

            if ($path === false) {
                throw new InvalidArgumentException('Could not store uploaded file.');
            }

            $absolute = Storage::disk('local')->path($path);
            $attachment = $this->toAiAttachment($absolute, $mime, $file->getClientOriginalName());

            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $mime,
                'size' => (int) $file->getSize(),
                'attachment' => $attachment,
            ];
        }

        return $stored;
    }

    protected function toAiAttachment(string $absolutePath, string $mime, string $originalName): File
    {
        if (str_starts_with($mime, 'image/')) {
            return Image::fromPath($absolutePath, $mime)->as($originalName);
        }

        return Document::fromPath($absolutePath)->as($originalName);
    }

    protected function assertMagicMime(UploadedFile $file, string $declaredMime): void
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException('Could not read uploaded file.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Could not read uploaded file.');
        }
        $header = fread($handle, 16) ?: '';
        fclose($handle);

        $ok = match (true) {
            $declaredMime === 'application/pdf' => str_starts_with($header, '%PDF'),
            str_starts_with($declaredMime, 'image/jpeg') => str_starts_with($header, "\xFF\xD8\xFF"),
            $declaredMime === 'image/png' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            $declaredMime === 'image/gif' => str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a'),
            $declaredMime === 'image/webp' => str_starts_with($header, 'RIFF') && str_contains(substr($header, 0, 16), 'WEBP'),
            in_array($declaredMime, ['text/plain', 'text/csv', 'application/csv'], true) => true,
            default => false,
        };

        if (! $ok) {
            throw new InvalidArgumentException(
                "{$file->getClientOriginalName()} content does not match its declared type.",
            );
        }
    }

    protected function assertAttachmentContentSafe(UploadedFile $file, string $mime): void
    {
        if (! in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true)) {
            return;
        }

        $contents = (string) file_get_contents($file->getRealPath() ?: '');
        if ($contents === '') {
            return;
        }

        // Cap scan size
        $sample = mb_substr($contents, 0, 8000);
        app(AiRiskGuard::class)->assertSafeUserText($sample, AiRiskGuard::SURFACE_CHAT, 8000);
    }

    /**
     * Prune attachment files older than configured TTL.
     */
    public function pruneExpired(): int
    {
        $days = (int) config('ai.security.attachment_ttl_days', 30);
        if ($days <= 0) {
            return 0;
        }

        $disk = Storage::disk('local');
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('ai-chat') as $path) {
            try {
                if ($disk->lastModified($path) < $cutoff) {
                    $disk->delete($path);
                    $deleted++;
                }
            } catch (\Throwable) {
                // continue
            }
        }

        return $deleted;
    }
}
