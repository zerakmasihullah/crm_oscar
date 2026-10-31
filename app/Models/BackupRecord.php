<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property string $trigger
 * @property string $retention_tier
 * @property string $disk
 * @property string $path
 * @property string|null $mirror_path
 * @property string $status
 * @property int|null $size
 * @property string|null $checksum
 * @property string|null $error
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class BackupRecord extends Model
{
    use SoftDeletes;
    use Trashable;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'trigger',
        'retention_tier',
        'disk',
        'path',
        'mirror_path',
        'status',
        'size',
        'checksum',
        'error',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * @return list<string>
     */
    public function restorableLayers(): array
    {
        return match ($this->type) {
            'full' => ['database', 'files', 'full'],
            'database' => ['database'],
            'files' => ['files'],
            default => ['database'],
        };
    }

    public function purgeTrashAssets(): void
    {
        try {
            Storage::disk($this->disk)->delete($this->path);
        } catch (\Throwable) {
            //
        }

        if ($this->mirror_path && config('backup.mirror_disk')) {
            try {
                Storage::disk(config('backup.mirror_disk'))->delete($this->mirror_path);
            } catch (\Throwable) {
                //
            }
        }
    }
}
