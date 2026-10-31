<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EfficientQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'audit.view');

        $search = $request->string('search')->trim()->toString();
        $event = $request->string('event')->trim()->toString();
        $logName = $request->string('log_name')->trim()->toString();
        $subjectType = $request->string('subject_type')->trim()->toString();
        $causerId = $request->string('causer_id')->trim()->toString();
        $attribute = $request->string('attribute')->trim()->toString();
        $hasChanges = $request->string('has_changes')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $perPage = min(100, max(10, $request->integer('per_page', 25)));

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->tap(fn (Builder $builder) => $this->applyFilters(
                $builder,
                $search,
                $event,
                $logName,
                $subjectType,
                $causerId,
                $attribute,
                $hasChanges,
                $dateFrom,
                $dateTo,
            ))
            ->orderByDesc('id');

        $logs = (clone $query)
            ->simplePaginate($perPage)
            ->withQueryString()
            ->through(fn (Activity $activity) => $this->transform($activity));

        return Inertia::render('settings/ActivityLogs/Index', [
            'logs' => $logs,
            'events' => $this->distinctEvents(),
            'logNames' => $this->distinctLogNames(),
            'subjectTypes' => $this->distinctSubjectTypes(),
            'attributes' => $this->distinctAttributes(),
            'causers' => $this->distinctCausers(),
            'summary' => $this->summary(),
            'filters' => [
                'search' => $search ?: null,
                'event' => $event ?: null,
                'log_name' => $logName ?: null,
                'subject_type' => $subjectType ?: null,
                'causer_id' => $causerId !== '' ? $causerId : null,
                'attribute' => $attribute ?: null,
                'has_changes' => in_array($hasChanges, ['0', '1'], true) ? $hasChanges : null,
                'date_from' => $dateFrom ?: null,
                'date_to' => $dateTo ?: null,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function applyFilters(
        Builder $query,
        string $search,
        string $event,
        string $logName,
        string $subjectType,
        string $causerId,
        string $attribute,
        string $hasChanges,
        string $dateFrom,
        string $dateTo,
    ): void {
        $query
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    EfficientQuery::applySearch(
                        $inner,
                        $search,
                        prefixColumns: ['description', 'event', 'log_name'],
                        containsColumns: ['description'],
                        exactColumns: ['event', 'log_name'],
                    );

                    $matchUser = function (Builder $userQuery) use ($search): void {
                        $escaped = addcslashes($search, '%_\\');
                        $userQuery->where(function (Builder $userInner) use ($escaped, $search): void {
                            $userInner->where('email', $search)
                                ->orWhere('name', 'like', "{$escaped}%")
                                ->orWhere('email', 'like', "{$escaped}%");
                        });
                    };

                    $inner->orWhereHasMorph('causer', [User::class], $matchUser);
                    $inner->orWhereHasMorph('subject', [User::class], $matchUser);
                });
            })
            ->when($event !== '', fn (Builder $builder) => $builder->where('event', $event))
            ->when($logName !== '', fn (Builder $builder) => $builder->where('log_name', $logName))
            ->when($subjectType !== '', function (Builder $builder) use ($subjectType): void {
                $builder->where(function (Builder $inner) use ($subjectType): void {
                    $inner->where('subject_type', $subjectType)
                        ->orWhere('subject_type', 'like', '%\\'.$subjectType);
                });
            })
            ->when($causerId === 'system', fn (Builder $builder) => $builder->whereNull('causer_id'))
            ->when(
                $causerId !== '' && $causerId !== 'system' && ctype_digit($causerId),
                fn (Builder $builder) => $builder
                    ->where('causer_type', User::class)
                    ->where('causer_id', (int) $causerId),
            )
            ->when($attribute !== '', function (Builder $builder) use ($attribute): void {
                $builder->where(function (Builder $inner) use ($attribute): void {
                    $inner->whereNotNull("properties->attributes->{$attribute}")
                        ->orWhereNotNull("properties->old->{$attribute}");
                });
            })
            ->when($hasChanges === '1', function (Builder $builder): void {
                $builder->where(function (Builder $inner): void {
                    $inner->whereRaw($this->jsonObjectNotEmptySql('properties', '$.attributes'))
                        ->orWhereRaw($this->jsonObjectNotEmptySql('properties', '$.old'));
                });
            })
            ->when($hasChanges === '0', function (Builder $builder): void {
                $builder->whereRaw($this->jsonObjectEmptyOrMissingSql('properties', '$.attributes'))
                    ->whereRaw($this->jsonObjectEmptyOrMissingSql('properties', '$.old'));
            })
            ->when($dateFrom !== '', function (Builder $builder) use ($dateFrom): void {
                $parsed = $this->parseDate($dateFrom);
                if ($parsed !== null) {
                    $builder->where('created_at', '>=', $parsed->startOfDay());
                }
            })
            ->when($dateTo !== '', function (Builder $builder) use ($dateTo): void {
                $parsed = $this->parseDate($dateTo);
                if ($parsed !== null) {
                    $builder->where('created_at', '<=', $parsed->endOfDay());
                }
            });
    }

    private function jsonObjectNotEmptySql(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return "JSON_LENGTH(JSON_EXTRACT({$column}, '{$path}')) > 0";
        }

        // sqlite + pgsql-compatible extract of object length via keys
        return "json_extract({$column}, '{$path}') IS NOT NULL AND json_extract({$column}, '{$path}') != '{}'";
    }

    private function jsonObjectEmptyOrMissingSql(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return "(JSON_EXTRACT({$column}, '{$path}') IS NULL OR JSON_LENGTH(JSON_EXTRACT({$column}, '{$path}')) = 0)";
        }

        return "(json_extract({$column}, '{$path}') IS NULL OR json_extract({$column}, '{$path}') = '{}')";
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function distinctEvents(): array
    {
        return Activity::query()
            ->whereNotNull('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function distinctLogNames(): array
    {
        return Activity::query()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function distinctSubjectTypes(): array
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type) => class_basename($type))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function distinctAttributes(): array
    {
        return Activity::query()
            ->whereNotNull('properties')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['properties'])
            ->flatMap(function (Activity $activity) {
                $properties = $activity->properties?->toArray() ?? [];
                $attributes = is_array($properties['attributes'] ?? null) ? array_keys($properties['attributes']) : [];
                $old = is_array($properties['old'] ?? null) ? array_keys($properties['old']) : [];

                return [...$attributes, ...$old];
            })
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function distinctCausers(): array
    {
        $ids = Activity::query()
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id')
            ->distinct()
            ->orderBy('causer_id')
            ->limit(200)
            ->pluck('causer_id')
            ->all();

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, today: int, updated: int, actors: int}
     */
    private function summary(): array
    {
        $today = now()->toDateString();

        return [
            'total' => Activity::query()->count(),
            'today' => Activity::query()->whereDate('created_at', $today)->count(),
            'updated' => Activity::query()->where('event', 'updated')->count(),
            'actors' => (int) Activity::query()
                ->whereNotNull('causer_id')
                ->selectRaw('count(distinct causer_id) as aggregate')
                ->value('aggregate'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Activity $activity): array
    {
        $causer = $activity->causer;
        $subject = $activity->subject;
        $properties = $activity->properties?->toArray() ?? [];

        return [
            'id' => $activity->id,
            'description' => $activity->description,
            'event' => $activity->event,
            'log_name' => $activity->log_name,
            'batch_uuid' => $activity->batch_uuid,
            'causer' => $causer instanceof User
                ? [
                    'id' => $causer->id,
                    'name' => $causer->name,
                    'email' => $causer->email,
                ]
                : ($causer instanceof Model
                    ? [
                        'id' => $causer->getKey(),
                        'name' => $this->modelLabel($causer),
                        'email' => null,
                    ]
                    : null),
            'subject_type' => $activity->subject_type
                ? class_basename($activity->subject_type)
                : null,
            'subject_id' => $activity->subject_id,
            'subject_label' => $subject instanceof Model
                ? $this->modelLabel($subject)
                : ($activity->subject_type
                    ? class_basename($activity->subject_type).'#'.$activity->subject_id
                    : null),
            'changes' => $this->changes($properties),
            'properties' => $properties,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return list<array{attribute: string, old: mixed, new: mixed}>
     */
    private function changes(array $properties): array
    {
        $attributes = is_array($properties['attributes'] ?? null)
            ? $properties['attributes']
            : [];
        $old = is_array($properties['old'] ?? null)
            ? $properties['old']
            : [];

        $keys = array_values(array_unique([
            ...array_keys($attributes),
            ...array_keys($old),
        ]));

        $changes = [];

        foreach ($keys as $key) {
            $changes[] = [
                'attribute' => (string) $key,
                'old' => array_key_exists($key, $old) ? $old[$key] : null,
                'new' => array_key_exists($key, $attributes) ? $attributes[$key] : null,
            ];
        }

        return $changes;
    }

    private function modelLabel(Model $model): string
    {
        foreach (['name', 'title', 'email', 'label'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return class_basename($model).'#'.$model->getKey();
    }
}
