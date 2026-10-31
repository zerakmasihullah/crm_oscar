<?php

namespace App\Services;

use InvalidArgumentException;

class LocaleRegistry
{
    public function __construct(
        private ?string $langPath = null,
    ) {}

    /**
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    public function all(): array
    {
        return array_merge($this->builtIn(), $this->custom());
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function exists(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    /**
     * @return array{name: string, native: string, dir: string}|null
     */
    public function get(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }

    public function direction(string $code): string
    {
        return $this->get($code)['dir'] ?? 'ltr';
    }

    /**
     * @return array<string, string>
     */
    public function directions(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $config, string $code): array => [
                $code => $config['dir'] ?? 'ltr',
            ])
            ->all();
    }

    /**
     * @param  array{name: string, native: string, dir: string}  $meta
     */
    public function register(string $code, array $meta): void
    {
        $code = strtolower(trim($code));

        if ($code === '' || ! preg_match('/^[a-z]{2,5}(-[a-z]{2,8})?$/', $code)) {
            throw new InvalidArgumentException('Invalid locale code.');
        }

        if ($this->exists($code)) {
            throw new InvalidArgumentException("Locale already exists: {$code}");
        }

        $dir = ($meta['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
        $name = trim((string) ($meta['name'] ?? ''));
        $native = trim((string) ($meta['native'] ?? ''));

        if ($name === '' || $native === '') {
            throw new InvalidArgumentException('Locale name and native label are required.');
        }

        $custom = $this->custom();
        $custom[$code] = [
            'name' => $name,
            'native' => $native,
            'dir' => $dir,
        ];

        $this->saveCustom($custom);
    }

    /**
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    private function builtIn(): array
    {
        /** @var array<string, array{name: string, native: string, dir: string}> $available */
        $available = config('locale.available', []);

        return $available;
    }

    /**
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    private function custom(): array
    {
        $path = $this->registryPath();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return [];
        }

        $locales = [];

        foreach ($data as $code => $meta) {
            if (! is_string($code) || ! is_array($meta)) {
                continue;
            }

            // Built-in locales always win over custom registry entries.
            if (array_key_exists($code, $this->builtIn())) {
                continue;
            }

            $locales[$code] = [
                'name' => (string) ($meta['name'] ?? $code),
                'native' => (string) ($meta['native'] ?? $meta['name'] ?? $code),
                'dir' => (($meta['dir'] ?? 'ltr') === 'rtl') ? 'rtl' : 'ltr',
            ];
        }

        return $locales;
    }

    /**
     * @param  array<string, array{name: string, native: string, dir: string}>  $locales
     */
    private function saveCustom(array $locales): void
    {
        ksort($locales);

        $json = json_encode($locales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $json .= "\n";

        file_put_contents($this->registryPath(), $json);
    }

    private function registryPath(): string
    {
        return ($this->langPath ?? lang_path()).'/locales.json';
    }
}
