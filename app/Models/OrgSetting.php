<?php

namespace App\Models;

use App\Concerns\Trashable;
use App\Support\CalendarSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OrgSetting extends Model
{
    use SoftDeletes;
    use Trashable;

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::remember("org_setting.{$key}", 3600, function () use ($key, $default) {
                $row = static::query()->where('key', $key)->first();

                return $row?->value ?? $default;
            });
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, mixed $value): void
    {
        $encoded = is_string($value)
            ? $value
            : json_encode($value, JSON_THROW_ON_ERROR);

        $rows = static::withTrashed()->where('key', $key)->orderByDesc('id')->get();
        $row = $rows->first();

        if ($row) {
            if (method_exists($row, 'trashed') && $row->trashed()) {
                $row->restore();
            }
            $row->update(['value' => $encoded]);

            // Collapse duplicate key rows (unique index is not enforced).
            $rows->slice(1)->each(function (self $extra): void {
                $extra->forceDelete();
            });
        } else {
            static::query()->create([
                'key' => $key,
                'value' => $encoded,
            ]);
        }

        Cache::forget("org_setting.{$key}");
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->get()->each->delete();
        Cache::forget("org_setting.{$key}");
    }

    /**
     * Soft-delete a branding asset setting while keeping the file on disk for Trash.
     * When restoring, an existing active setting with the same key is soft-deleted first.
     */
    public static function trashBrandingPath(string $key): ?self
    {
        $row = static::query()->where('key', $key)->first();

        if ($row === null) {
            return null;
        }

        $row->delete();
        Cache::forget("org_setting.{$key}");

        return $row;
    }

    public function restoreFromTrash(): void
    {
        if (in_array($this->key, ['logo_path', 'favicon_path'], true)) {
            static::query()
                ->where('key', $this->key)
                ->whereKeyNot($this->getKey())
                ->get()
                ->each->delete();
        }

        $this->restore();
        Cache::forget("org_setting.{$this->key}");
    }

    public function trashTitle(): string
    {
        if (in_array($this->key, ['logo_path', 'favicon_path'], true)) {
            $label = $this->key === 'logo_path' ? 'Logo' : 'Favicon';
            $path = is_string($this->value) ? basename($this->value) : '';

            return $path !== '' ? "{$label}: {$path}" : $label;
        }

        return (string) $this->key;
    }

    public function purgeTrashAssets(): void
    {
        if (! in_array($this->key, ['logo_path', 'favicon_path'], true)) {
            return;
        }

        $path = is_string($this->value) && $this->value !== '' ? $this->value : null;

        if ($path === null) {
            return;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/')
            || str_starts_with($path, 'data:')
        ) {
            return;
        }

        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable) {
            //
        }
    }

    public static function logoPath(): ?string
    {
        return self::storedPath('logo_path');
    }

    public static function logoUrl(): ?string
    {
        return self::publicUrl(self::logoPath());
    }

    /**
     * Absolute or data-URI logo for email clients (relative /storage URLs do not work in mail).
     */
    public static function logoUrlForMail(): ?string
    {
        $path = self::logoPath();

        if ($path === null) {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:')
        ) {
            return $path;
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
            $contents = Storage::disk('public')->get($path);

            if (! is_string($contents) || $contents === '') {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable) {
            return self::publicUrl($path, absolute: true);
        }
    }

    public static function faviconPath(): ?string
    {
        return self::storedPath('favicon_path');
    }

    public static function faviconUrl(): ?string
    {
        return self::publicUrl(self::faviconPath());
    }

    /**
     * @return array{app_name: string, default_locale: string, calendar_system: string, logo_url: string|null, favicon_url: string|null}
     */
    public static function general(): array
    {
        return [
            'app_name' => (string) static::getValue('app_name', config('app.name')),
            'default_locale' => (string) static::getValue('default_locale', config('app.locale')),
            'calendar_system' => CalendarSystem::current(),
            'logo_url' => static::logoUrl(),
            'favicon_url' => static::faviconUrl(),
        ];
    }

    protected static function storedPath(string $key): ?string
    {
        $path = static::getValue($key);

        return is_string($path) && $path !== '' ? $path : null;
    }

    protected static function publicUrl(?string $path, bool $absolute = false): ?string
    {
        if ($path === null) {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:')
        ) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $absolute ? rtrim((string) config('app.url'), '/').$path : $path;
        }

        // Root-relative so the logo works regardless of APP_URL host/port mismatch.
        $relative = '/storage/'.ltrim($path, '/');

        if (! $absolute) {
            return $relative;
        }

        return rtrim((string) config('app.url'), '/').$relative;
    }
}
