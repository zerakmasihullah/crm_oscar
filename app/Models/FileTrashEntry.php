<?php

namespace App\Models;

use App\Concerns\Trashable;
use App\Services\FileManagerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FileTrashEntry extends Model
{
    use SoftDeletes;
    use Trashable;

    protected $fillable = [
        'root',
        'original_path',
        'trash_path',
        'name',
        'is_directory',
        'size',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_directory' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function trashTitle(): string
    {
        return $this->name !== '' ? $this->name : $this->original_path;
    }

    /**
     * Move the file back to its original path, then remove this registry row.
     */
    public function restoreFromTrash(): void
    {
        app(FileManagerService::class)->restoreFromTrash($this);
        $this->forceDelete();
    }

    public function purgeTrashAssets(): void
    {
        $files = app(FileManagerService::class);
        $absolute = $files->trashAbsolutePath($this->root, $this->trash_path);

        if (! file_exists($absolute)) {
            return;
        }

        if (is_dir($absolute)) {
            if (! File::deleteDirectory($absolute)) {
                throw new RuntimeException(__('Could not permanently delete the trashed folder.'));
            }

            return;
        }

        if (! File::delete($absolute)) {
            throw new RuntimeException(__('Could not permanently delete the trashed file.'));
        }
    }
}
