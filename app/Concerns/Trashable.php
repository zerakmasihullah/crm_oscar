<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Marks a SoftDeletes model as visible in the central Trash UI.
 *
 * Models using this trait should also use SoftDeletes. Override trashTitle()
 * when the default title column from config/trash.php is insufficient.
 */
trait Trashable
{
    public static function bootTrashable(): void
    {
        static::forceDeleting(function ($model): void {
            if (method_exists($model, 'purgeTrashAssets')) {
                $model->purgeTrashAssets();
            }
        });
    }

    public function trashTypeKey(): string
    {
        foreach (config('trash.models', []) as $key => $entry) {
            if (($entry['model'] ?? null) === static::class) {
                return (string) $key;
            }
        }

        return class_basename(static::class);
    }

    public function trashTypeLabel(): string
    {
        $key = $this->trashTypeKey();
        $entry = config("trash.models.{$key}", []);

        return (string) ($entry['label'] ?? class_basename(static::class));
    }

    public function trashTitle(): string
    {
        $key = $this->trashTypeKey();
        $column = config("trash.models.{$key}.title");

        if (is_string($column) && $column !== '') {
            $value = data_get($this, $column);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 120);
            }

            if (is_scalar($value) && (string) $value !== '') {
                return mb_substr((string) $value, 0, 120);
            }
        }

        return '#'.$this->getKey();
    }

    /**
     * Permanently remove related disk assets. Called before forceDelete.
     */
    public function purgeTrashAssets(): void
    {
        //
    }

    /**
     * @return array{type: string, label: string, id: string|int, title: string, deleted_at: string|null}
     */
    public function toTrashArray(): array
    {
        /** @var SoftDeletes $this */
        return [
            'type' => $this->trashTypeKey(),
            'label' => $this->trashTypeLabel(),
            'id' => $this->getKey(),
            'title' => $this->trashTitle(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
