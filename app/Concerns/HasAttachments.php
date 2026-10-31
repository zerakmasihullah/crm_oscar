<?php

namespace App\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function storeAttachment(
        UploadedFile $file,
        ?string $title = null,
        ?int $uploadedBy = null,
        ?string $clientUuid = null,
    ): Attachment {
        if ($clientUuid) {
            $existing = Attachment::query()->where('client_uuid', $clientUuid)->first();

            if ($existing) {
                return $existing;
            }
        }

        $path = $file->store('attachments/'.class_basename($this).'/'.$this->getKey(), 'local');

        return $this->attachments()->create([
            'client_uuid' => $clientUuid,
            'title' => $title,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize() ?: null,
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => $uploadedBy ?? auth()->id(),
        ]);
    }

    /**
     * Store an already-assembled file as an attachment (chunked uploads).
     */
    public function storeAttachmentFromPath(
        string $absolutePath,
        string $originalFilename,
        ?string $mime = null,
        ?int $size = null,
        ?int $uploadedBy = null,
        ?string $clientUuid = null,
        ?string $title = null,
    ): Attachment {
        if ($clientUuid) {
            $existing = Attachment::query()->where('client_uuid', $clientUuid)->first();

            if ($existing) {
                return $existing;
            }
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new \RuntimeException(__('Assembled attachment file is missing.'));
        }

        $directory = 'attachments/'.class_basename($this).'/'.$this->getKey();
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $relative = $directory.'/'.$basename;

        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException(__('Could not read assembled attachment.'));
        }

        try {
            Storage::disk('local')->put($relative, $stream);
        } finally {
            fclose($stream);
        }

        return $this->attachments()->create([
            'client_uuid' => $clientUuid,
            'title' => $title,
            'file_path' => $relative,
            'original_filename' => $originalFilename,
            'file_size' => $size ?? (filesize($absolutePath) ?: null),
            'mime_type' => $mime,
            'uploaded_by' => $uploadedBy ?? auth()->id(),
        ]);
    }

    /**
     * Replace an existing attachment: store the new file, then soft-delete the old row (file kept until force purge).
     */
    public function replaceAttachment(
        Attachment $old,
        string $absolutePath,
        string $originalFilename,
        ?string $mime = null,
        ?int $size = null,
        ?int $uploadedBy = null,
        ?string $title = null,
    ): Attachment {
        if ((int) $old->attachable_id !== (int) $this->getKey()
            || $old->attachable_type !== $this->getMorphClass()) {
            throw new \InvalidArgumentException(__('Attachment does not belong to this record.'));
        }

        $new = $this->storeAttachmentFromPath(
            $absolutePath,
            $originalFilename,
            $mime,
            $size,
            $uploadedBy,
            null,
            $title ?? $old->title,
        );

        $old->delete();

        return $new;
    }

    protected static function bootHasAttachments(): void
    {
        static::deleting(function ($model): void {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                foreach ($model->attachments()->withTrashed()->get() as $attachment) {
                    $attachment->deleteFile();
                    $attachment->forceDelete();
                }

                return;
            }

            foreach ($model->attachments as $attachment) {
                $attachment->delete();
            }
        });

        static::restoring(function ($model): void {
            foreach ($model->attachments()->onlyTrashed()->get() as $attachment) {
                $attachment->restore();
            }
        });
    }
}
