<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UploadSession extends Model
{
    use HasUuids;
    use SoftDeletes;
    use Trashable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_ASSEMBLING = 'assembling';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABORTED = 'aborted';

    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UPLOADING,
        self::STATUS_ASSEMBLING,
    ];

    protected $fillable = [
        'user_id',
        'original_filename',
        'safe_filename',
        'extension',
        'declared_mime',
        'detected_mime',
        'total_bytes',
        'chunk_bytes',
        'total_chunks',
        'received_chunks',
        'received_bytes',
        'checksum_algo',
        'expected_checksum',
        'actual_checksum',
        'status',
        'destination',
        'destination_root',
        'destination_path',
        'final_path',
        'chunk_disk',
        'chunk_directory',
        'error_message',
        'meta',
        'expires_at',
        'assembled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_bytes' => 'integer',
            'chunk_bytes' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'array',
            'received_bytes' => 'integer',
            'meta' => 'array',
            'expires_at' => 'datetime',
            'assembled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return list<int> */
    public function receivedChunkIndexes(): array
    {
        $chunks = $this->received_chunks ?? [];

        return array_values(array_unique(array_map('intval', $chunks)));
    }

    /** @return list<int> */
    public function missingChunkIndexes(): array
    {
        $received = array_flip($this->receivedChunkIndexes());
        $missing = [];

        for ($i = 0; $i < $this->total_chunks; $i++) {
            if (! isset($received[$i])) {
                $missing[] = $i;
            }
        }

        return $missing;
    }

    public function progressPercent(): float
    {
        if ($this->total_bytes <= 0) {
            return 100.0;
        }

        return round(min(100, ($this->received_bytes / $this->total_bytes) * 100), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'original_filename' => $this->original_filename,
            'safe_filename' => $this->safe_filename,
            'total_bytes' => $this->total_bytes,
            'chunk_bytes' => $this->chunk_bytes,
            'total_chunks' => $this->total_chunks,
            'received_chunks' => $this->receivedChunkIndexes(),
            'missing_chunks' => $this->missingChunkIndexes(),
            'received_bytes' => $this->received_bytes,
            'progress_percent' => $this->progressPercent(),
            'checksum_algo' => $this->checksum_algo,
            'expected_checksum' => $this->expected_checksum,
            'actual_checksum' => $this->actual_checksum,
            'destination' => $this->destination,
            'destination_root' => $this->destination_root,
            'destination_path' => $this->destination_path,
            'final_path' => $this->final_path,
            'detected_mime' => $this->detected_mime,
            'error_message' => $this->error_message,
            'attachment_id' => $this->meta['attachment_id'] ?? null,
            'download_url' => $this->meta['download_url'] ?? null,
            'replaced_attachment_id' => $this->meta['replaced_attachment_id'] ?? null,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
