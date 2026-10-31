<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TrashRegistry
{
    /**
     * @return list<array{key: string, label: string, model: class-string<Model>}>
     */
    public function types(): array
    {
        $types = [];

        foreach (config('trash.models', []) as $key => $entry) {
            $model = $entry['model'] ?? null;

            if (! is_string($model) || ! class_exists($model)) {
                continue;
            }

            $types[] = [
                'key' => (string) $key,
                'label' => (string) ($entry['label'] ?? $key),
                'model' => $model,
            ];
        }

        return $types;
    }

    /**
     * @return class-string<Model&SoftDeletes>
     */
    public function modelForType(string $type): string
    {
        $entry = config("trash.models.{$type}");

        if (! is_array($entry) || ! isset($entry['model']) || ! class_exists($entry['model'])) {
            throw new InvalidArgumentException("Unknown trash type [{$type}].");
        }

        $model = $entry['model'];

        if (! in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException("Trash type [{$type}] does not use SoftDeletes.");
        }

        return $model;
    }

    public function resolve(string $type, string|int $id): Model
    {
        $modelClass = $this->modelForType($type);

        /** @var Model&SoftDeletes $model */
        $model = $modelClass::onlyTrashed()->whereKey($id)->firstOrFail();

        return $model;
    }

    /**
     * Aggregate trashed rows across registered models (newest first).
     *
     * @return Collection<int, array{type: string, label: string, id: string|int, title: string, deleted_at: string|null}>
     */
    public function collect(?string $type = null, ?string $search = null): Collection
    {
        $items = collect();

        foreach ($this->types() as $entry) {
            if ($type !== null && $type !== '' && $entry['key'] !== $type) {
                continue;
            }

            $modelClass = $entry['model'];

            if (! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                continue;
            }

            /** @var Model&SoftDeletes $modelClass */
            $query = $modelClass::onlyTrashed()->orderByDesc('deleted_at');

            if ($search !== null && $search !== '') {
                $titleColumn = config("trash.models.{$entry['key']}.title");

                if (is_string($titleColumn) && $titleColumn !== '') {
                    $query->where($titleColumn, 'like', '%'.$search.'%');
                }
            }

            foreach ($query->limit(500)->get() as $model) {
                if (method_exists($model, 'toTrashArray')) {
                    $items->push($model->toTrashArray());
                }
            }
        }

        return $items
            ->sortByDesc(fn (array $row) => $row['deleted_at'] ?? '')
            ->values();
    }

    public function restore(string $type, string|int $id): Model
    {
        $model = $this->resolve($type, $id);

        if (method_exists($model, 'restoreFromTrash')) {
            $model->restoreFromTrash();
        } else {
            $model->restore();
        }

        return $model;
    }

    public function forceDestroy(string $type, string|int $id): void
    {
        $model = $this->resolve($type, $id);
        $model->forceDelete();
    }

    /**
     * Permanently delete all trashed rows older than retention.
     */
    public function prune(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('trash.retention_days', 60));
        $cutoff = now()->subDays($days);
        $purged = 0;

        foreach ($this->types() as $entry) {
            $modelClass = $entry['model'];

            if (! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                continue;
            }

            /** @var Model&SoftDeletes $modelClass */
            $modelClass::onlyTrashed()
                ->where('deleted_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(50, function ($models) use (&$purged): void {
                    foreach ($models as $model) {
                        $model->forceDelete();
                        $purged++;
                    }
                });
        }

        return $purged;
    }

    public function emptyAll(): int
    {
        $purged = 0;

        foreach ($this->types() as $entry) {
            $modelClass = $entry['model'];

            if (! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                continue;
            }

            /** @var Model&SoftDeletes $modelClass */
            $modelClass::onlyTrashed()
                ->orderBy('id')
                ->chunkById(50, function ($models) use (&$purged): void {
                    foreach ($models as $model) {
                        $model->forceDelete();
                        $purged++;
                    }
                });
        }

        return $purged;
    }

    /**
     * @return array{total: int, today: int, types: int, retention_days: int}
     */
    public function summary(): array
    {
        $total = 0;
        $today = 0;
        $typesWithItems = 0;
        $todayStart = now()->startOfDay();

        foreach ($this->types() as $entry) {
            $modelClass = $entry['model'];

            if (! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                continue;
            }

            /** @var Model&SoftDeletes $modelClass */
            $count = $modelClass::onlyTrashed()->count();

            if ($count === 0) {
                continue;
            }

            $typesWithItems++;
            $total += $count;
            $today += $modelClass::onlyTrashed()
                ->where('deleted_at', '>=', $todayStart)
                ->count();
        }

        return [
            'total' => $total,
            'today' => $today,
            'types' => $typesWithItems,
            'retention_days' => (int) config('trash.retention_days', 60),
        ];
    }
}
