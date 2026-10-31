<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SystemLogReader
{
    public const LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /** Max bytes scanned from the end of a log file (keeps huge files usable). */
    public const MAX_SCAN_BYTES = 8_388_608; // 8 MB

    protected const ENTRY_PATTERN = '/^\[(?P<datetime>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?)\] (?P<env>\S+)\.(?P<level>\w+): (?P<message>.*)$/';

    /**
     * @return list<array{name: string, size: int, modified_at: string}>
     */
    public function listFiles(): array
    {
        $directory = storage_path('logs');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.log'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ])
            ->all();
    }

    public function resolvePath(?string $filename): ?string
    {
        $files = $this->listFiles();

        if ($files === []) {
            return null;
        }

        if ($filename === null || $filename === '') {
            return storage_path('logs/'.$files[0]['name']);
        }

        $safe = basename($filename);

        if ($safe !== $filename || ! str_ends_with(strtolower($safe), '.log')) {
            return null;
        }

        $path = storage_path('logs/'.$safe);

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $realLogs = realpath(storage_path('logs'));
        $realPath = realpath($path);

        if ($realLogs === false || $realPath === false || (! str_starts_with($realPath, $realLogs.DIRECTORY_SEPARATOR) && $realPath !== $realLogs)) {
            return null;
        }

        return $realPath;
    }

    /**
     * @param  array{file?: ?string, level?: ?string, search?: ?string, date_from?: ?string, date_to?: ?string, page?: int, per_page?: int}  $filters
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int, from: ?int, to: ?int},
     *     links: list<array{url: ?string, label: string, active: boolean}>,
     *     scan: array{bytes_scanned: int, file_size: int, truncated: bool, matched: int}
     * }
     */
    public function query(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 50)));
        $level = strtolower(trim((string) ($filters['level'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $this->parseDate($filters['date_from'] ?? null, startOfDay: true);
        $dateTo = $this->parseDate($filters['date_to'] ?? null, startOfDay: false);

        if ($level !== '' && ! in_array($level, self::LEVELS, true)) {
            $level = '';
        }

        $path = $this->resolvePath($filters['file'] ?? null);

        if ($path === null) {
            return $this->emptyPage($page, $perPage);
        }

        [$entries, $bytesScanned, $fileSize, $truncated] = $this->parseTail($path);

        $matched = [];

        foreach ($entries as $entry) {
            if ($level !== '' && $entry['level'] !== $level) {
                continue;
            }

            if ($dateFrom !== null && ($entry['logged_at'] === null || $entry['logged_at'] < $dateFrom)) {
                continue;
            }

            if ($dateTo !== null && ($entry['logged_at'] === null || $entry['logged_at'] > $dateTo)) {
                continue;
            }

            if ($search !== '' && ! $this->matchesSearch($entry, $search)) {
                continue;
            }

            $matched[] = $entry;
        }

        // Newest first
        $matched = array_reverse($matched);
        $total = count($matched);
        $lastPage = max(1, (int) ceil(max($total, 1) / $perPage));
        if ($total === 0) {
            $lastPage = 1;
        }
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($matched, $offset, $perPage);
        $from = $total === 0 ? null : $offset + 1;
        $to = $total === 0 ? null : $offset + count($slice);

        return [
            'entries' => array_values($slice),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
            'links' => $this->buildLinks($page, $lastPage, $filters),
            'scan' => [
                'bytes_scanned' => $bytesScanned,
                'file_size' => $fileSize,
                'truncated' => $truncated,
                'matched' => $total,
            ],
        ];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: int, 3: bool}
     */
    protected function parseTail(string $path): array
    {
        $fileSize = filesize($path) ?: 0;

        if ($fileSize === 0) {
            return [[], 0, 0, false];
        }

        $truncated = $fileSize > self::MAX_SCAN_BYTES;
        $start = $truncated ? $fileSize - self::MAX_SCAN_BYTES : 0;

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [[], 0, $fileSize, $truncated];
        }

        try {
            fseek($handle, $start);

            if ($truncated) {
                // Drop the partial first line so we start on a real entry boundary.
                fgets($handle);
            }

            $content = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if ($content === false || $content === '') {
            return [[], 0, $fileSize, $truncated];
        }

        $bytesScanned = strlen($content);
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match(self::ENTRY_PATTERN, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $this->finalizeEntry($current);
                }

                $current = [
                    'datetime' => $matches['datetime'],
                    'logged_at' => $matches['datetime'],
                    'environment' => $matches['env'],
                    'level' => strtolower($matches['level']),
                    'message' => $matches['message'],
                    'message_full' => $matches['message'],
                    'stack' => null,
                    'raw' => $line,
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            $current['raw'] .= "\n".$line;

            if ($current['stack'] === null && (str_contains($line, '[stacktrace]') || str_starts_with(ltrim($line), '#'))) {
                $current['stack'] = $line;
            } elseif ($current['stack'] !== null) {
                $current['stack'] .= "\n".$line;
            } else {
                $current['message_full'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->finalizeEntry($current);
        }

        return [$entries, $bytesScanned, $fileSize, $truncated];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function finalizeEntry(array $entry): array
    {
        $message = (string) ($entry['message'] ?? '');
        $messageFull = (string) ($entry['message_full'] ?? $message);
        $stack = is_string($entry['stack'] ?? null) ? (string) $entry['stack'] : null;
        $hasStack = $stack !== null && $stack !== '';

        return [
            'id' => md5(($entry['datetime'] ?? '').'|'.($entry['level'] ?? '').'|'.Str::limit($message, 120)),
            'datetime' => $entry['datetime'] ?? null,
            'logged_at' => $entry['logged_at'] ?? null,
            'environment' => $entry['environment'] ?? null,
            'level' => $entry['level'] ?? 'info',
            'message' => $this->truncate($message, 280),
            'message_full' => $this->truncate($messageFull, 4_000),
            'has_stack' => $hasStack,
            'stack' => $hasStack ? $this->truncate($stack, 8_000) : null,
            'raw' => $this->truncate((string) ($entry['raw'] ?? ''), 12_000),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function matchesSearch(array $entry, string $search): bool
    {
        $haystack = strtolower(
            ($entry['message_full'] ?? '')."\n".($entry['stack'] ?? '')."\n".($entry['environment'] ?? '')."\n".($entry['level'] ?? '')
        );

        return str_contains($haystack, strtolower($search));
    }

    protected function parseDate(mixed $value, bool $startOfDay): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse(trim($value));

            return ($startOfDay ? $date->startOfDay() : $date->endOfDay())->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function truncate(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit)."\n…";
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{url: ?string, label: string, active: boolean}>
     */
    protected function buildLinks(int $page, int $lastPage, array $filters): array
    {
        $query = array_filter([
            'file' => $filters['file'] ?? null,
            'level' => $filters['level'] ?? null,
            'search' => $filters['search'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'per_page' => $filters['per_page'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $make = function (int $target) use ($query): string {
            return route('settings.system-logs.index', [...$query, 'page' => $target]);
        };

        $links = [
            [
                'url' => $page > 1 ? $make($page - 1) : null,
                'label' => '&laquo; Previous',
                'active' => false,
            ],
        ];

        $window = 2;
        $start = max(1, $page - $window);
        $end = min($lastPage, $page + $window);

        if ($start > 1) {
            $links[] = ['url' => $make(1), 'label' => '1', 'active' => false];
            if ($start > 2) {
                $links[] = ['url' => null, 'label' => '...', 'active' => false];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $links[] = [
                'url' => $make($i),
                'label' => (string) $i,
                'active' => $i === $page,
            ];
        }

        if ($end < $lastPage) {
            if ($end < $lastPage - 1) {
                $links[] = ['url' => null, 'label' => '...', 'active' => false];
            }
            $links[] = ['url' => $make($lastPage), 'label' => (string) $lastPage, 'active' => false];
        }

        $links[] = [
            'url' => $page < $lastPage ? $make($page + 1) : null,
            'label' => 'Next &raquo;',
            'active' => false,
        ];

        return $links;
    }

    /**
     * @return array{
     *     entries: list<array<string, mixed>>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int, from: ?int, to: ?int},
     *     links: list<array{url: ?string, label: string, active: boolean}>,
     *     scan: array{bytes_scanned: int, file_size: int, truncated: bool, matched: int}
     * }
     */
    protected function emptyPage(int $page, int $perPage): array
    {
        return [
            'entries' => [],
            'meta' => [
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
            'links' => [],
            'scan' => [
                'bytes_scanned' => 0,
                'file_size' => 0,
                'truncated' => false,
                'matched' => 0,
            ],
        ];
    }
}
