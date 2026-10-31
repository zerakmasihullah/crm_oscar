<?php

namespace App\Services;

use App\Models\FileTrashEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileManagerService
{
    public const ROOTS = [
        'public' => [
            'label' => 'Public storage',
            'description' => 'Web-accessible files such as branding and uploads',
            'disk' => 'public',
            'path' => 'app/public',
        ],
        'private' => [
            'label' => 'Private storage',
            'description' => 'Protected attachments and internal files',
            'disk' => 'local',
            'path' => 'app/private',
        ],
    ];

    public const TEXT_EXTENSIONS = [
        'txt', 'md', 'markdown', 'json', 'csv', 'log', 'xml', 'yml', 'yaml',
        'env', 'ini', 'conf', 'css', 'js', 'ts', 'vue', 'php', 'html', 'htm',
        'svg', 'sql', 'gitignore', 'htaccess',
    ];

    public const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    ];

    public const MAX_PREVIEW_BYTES = 524_288; // 512 KB

    /** Simple (non-chunked) multipart ceiling — large files use ChunkedUploadService. */
    public const MAX_UPLOAD_BYTES = 20_971_520; // 20 MB

    /** @return list<array{key: string, label: string, description: string, exists: bool, file_count: int, folder_count: int, total_bytes: int}> */
    public function roots(): array
    {
        $items = [];

        foreach (self::ROOTS as $key => $meta) {
            $absolute = $this->rootAbsolutePath($key);
            $exists = is_dir($absolute);
            $stats = $exists ? $this->directoryStats($absolute) : [
                'file_count' => 0,
                'folder_count' => 0,
                'total_bytes' => 0,
            ];

            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'exists' => $exists,
                'file_count' => $stats['file_count'],
                'folder_count' => $stats['folder_count'],
                'total_bytes' => $stats['total_bytes'],
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     root: string,
     *     path: string,
     *     breadcrumbs: list<array{label: string, path: string}>,
     *     entries: list<array<string, mixed>>,
     *     parent_path: string|null,
     *     summary: array{folders: int, files: int, total_bytes: int}
     * }
     */
    public function list(string $root, string $relativePath = '', ?string $search = null): array
    {
        $this->assertRoot($root);
        $directory = $this->resolveDirectory($root, $relativePath);
        $search = $search !== null ? trim($search) : null;
        $search = $search === '' ? null : $search;

        $entries = [];

        if ($search !== null) {
            $entries = $this->searchEntries($root, $directory, $search);
        } else {
            foreach (File::directories($directory) as $folder) {
                if ($this->isTrashDirectoryName(basename($folder))) {
                    continue;
                }

                $entries[] = $this->mapEntry($root, $folder, true);
            }

            foreach (File::files($directory) as $file) {
                $entries[] = $this->mapEntry($root, $file->getPathname(), false);
            }
        }

        usort($entries, function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        $folders = count(array_filter($entries, fn (array $e) => $e['type'] === 'folder'));
        $files = count($entries) - $folders;
        $totalBytes = array_sum(array_map(fn (array $e) => (int) ($e['size'] ?? 0), $entries));

        return [
            'root' => $root,
            'path' => $this->normalizeRelativePath($relativePath),
            'breadcrumbs' => $this->breadcrumbs($root, $relativePath),
            'entries' => $entries,
            'parent_path' => $this->parentPath($relativePath),
            'summary' => [
                'folders' => $folders,
                'files' => $files,
                'total_bytes' => $totalBytes,
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     path: string,
     *     type: string,
     *     extension: string|null,
     *     size: int,
     *     mime: string|null,
     *     modified_at: string,
     *     is_text: bool,
     *     is_image: bool,
     *     previewable: bool,
     *     content: string|null,
     *     truncated: bool,
     *     public_url: string|null
     * }
     */
    public function inspect(string $root, string $relativePath): array
    {
        $absolute = $this->resolveFile($root, $relativePath);
        $entry = $this->mapEntry($root, $absolute, false);
        $extension = $entry['extension'];
        $isText = $this->isTextExtension($extension);
        $isImage = $this->isImageExtension($extension);
        $size = (int) $entry['size'];
        $content = null;
        $truncated = false;

        if ($isText && $size > 0) {
            if ($size > self::MAX_PREVIEW_BYTES) {
                $truncated = true;
                $handle = fopen($absolute, 'rb');
                $content = $handle === false ? null : (string) fread($handle, self::MAX_PREVIEW_BYTES);

                if ($handle !== false) {
                    fclose($handle);
                }
            } else {
                $content = File::get($absolute);
            }
        }

        return [
            ...$entry,
            'is_text' => $isText,
            'is_image' => $isImage,
            'previewable' => $isText || $isImage,
            'content' => $content,
            'truncated' => $truncated,
            'public_url' => $root === 'public' ? $this->publicUrl($relativePath) : null,
        ];
    }

    public function createDirectory(string $root, string $relativePath, string $name): string
    {
        $name = $this->sanitizeName($name);
        $parent = $this->resolveDirectory($root, $relativePath);
        $target = $parent.DIRECTORY_SEPARATOR.$name;

        if (file_exists($target)) {
            throw new InvalidArgumentException(__('A file or folder with that name already exists.'));
        }

        if (! File::makeDirectory($target, 0755, false, true)) {
            throw new RuntimeException(__('Could not create the folder.'));
        }

        return $this->toRelative($root, $target);
    }

    public function upload(string $root, string $relativePath, UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException(__('The file is too large to upload. Use the resumable uploader for large files.'));
        }

        $name = $this->sanitizeName($file->getClientOriginalName());

        return $this->placeFile($root, $relativePath, $name, function (string $parent, string $finalName) use ($file): void {
            try {
                $file->move($parent, $finalName);
            } catch (FileException $e) {
                throw new RuntimeException(__('Could not upload the file.'), 0, $e);
            }
        });
    }

    /**
     * Place an already-assembled file into a storage root (used by chunked uploads).
     */
    public function storeAssembledFile(
        string $root,
        string $relativePath,
        string $absoluteSource,
        string $filename,
    ): string {
        if (! is_file($absoluteSource) || ! is_readable($absoluteSource)) {
            throw new RuntimeException(__('Assembled file is missing or unreadable.'));
        }

        $name = $this->sanitizeName($filename);

        return $this->placeFile($root, $relativePath, $name, function (string $parent, string $finalName) use ($absoluteSource): void {
            $target = $parent.DIRECTORY_SEPARATOR.$finalName;

            if (! @rename($absoluteSource, $target) && ! @copy($absoluteSource, $target)) {
                throw new RuntimeException(__('Could not store the assembled file.'));
            }

            @chmod($target, 0644);
        });
    }

    /**
     * @param  callable(string $parent, string $finalName): void  $writer
     */
    protected function placeFile(string $root, string $relativePath, string $name, callable $writer): string
    {
        $parent = $this->resolveDirectory($root, $relativePath);
        $finalName = $name;
        $target = $parent.DIRECTORY_SEPARATOR.$finalName;

        if (file_exists($target)) {
            $finalName = $this->uniqueName($parent, $name);
            $target = $parent.DIRECTORY_SEPARATOR.$finalName;
        }

        $writer($parent, $finalName);

        if (! is_file($target)) {
            throw new RuntimeException(__('Could not store the file.'));
        }

        return $this->toRelative($root, $target);
    }

    public function assertDirectory(string $root, string $relativePath): void
    {
        $this->resolveDirectory($root, $relativePath);
    }

    public function rename(string $root, string $relativePath, string $newName): string
    {
        $newName = $this->sanitizeName($newName);
        $absolute = $this->resolveExisting($root, $relativePath);
        $parent = dirname($absolute);
        $target = $parent.DIRECTORY_SEPARATOR.$newName;

        if (basename($absolute) === $newName) {
            return $this->normalizeRelativePath($relativePath);
        }

        if (file_exists($target)) {
            throw new InvalidArgumentException(__('A file or folder with that name already exists.'));
        }

        if (! File::move($absolute, $target)) {
            throw new RuntimeException(__('Could not rename the item.'));
        }

        return $this->toRelative($root, $target);
    }

    public function move(string $root, string $relativePath, string $destinationDirectory): string
    {
        $absolute = $this->resolveExisting($root, $relativePath);
        $destination = $this->resolveDirectory($root, $destinationDirectory);
        $name = basename($absolute);
        $target = $destination.DIRECTORY_SEPARATOR.$name;

        if (realpath($absolute) === realpath($destination)) {
            throw new InvalidArgumentException(__('Cannot move an item into itself.'));
        }

        if (is_dir($absolute)) {
            $realSource = realpath($absolute);
            $realDest = realpath($destination);

            if ($realSource !== false && $realDest !== false && str_starts_with($realDest, $realSource.DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException(__('Cannot move a folder into one of its subfolders.'));
            }
        }

        if (file_exists($target)) {
            throw new InvalidArgumentException(__('A file or folder with that name already exists in the destination.'));
        }

        if (! File::move($absolute, $target)) {
            throw new RuntimeException(__('Could not move the item.'));
        }

        return $this->toRelative($root, $target);
    }

    public function delete(string $root, string $relativePath): void
    {
        $this->moveToTrash($root, $relativePath);
    }

    public function moveToTrash(string $root, string $relativePath, ?int $deletedBy = null): FileTrashEntry
    {
        $absolute = $this->resolveExisting($root, $relativePath);
        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '' || str_starts_with($normalized, $this->trashDirname())) {
            throw new InvalidArgumentException(__('Cannot trash this path.'));
        }

        $isDirectory = is_dir($absolute);
        $name = basename($absolute);
        $size = $isDirectory ? null : (int) filesize($absolute);
        $uuid = (string) Str::uuid();
        $trashRelative = $this->trashDirname().'/'.$uuid.'/'.$name;
        $trashAbsolute = $this->rootAbsolutePath($root).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $trashRelative);

        File::ensureDirectoryExists(dirname($trashAbsolute));

        if (! File::move($absolute, $trashAbsolute)) {
            throw new RuntimeException(__('Could not move the item to trash.'));
        }

        $entry = FileTrashEntry::query()->create([
            'root' => $root,
            'original_path' => $normalized,
            'trash_path' => $trashRelative,
            'name' => $name,
            'is_directory' => $isDirectory,
            'size' => $size,
            'deleted_by' => $deletedBy ?? auth()->id(),
        ]);

        $entry->delete();

        return $entry;
    }

    public function restoreFromTrash(FileTrashEntry $entry): void
    {
        $trashAbsolute = $this->trashAbsolutePath($entry->root, $entry->trash_path);

        if (! file_exists($trashAbsolute)) {
            throw new RuntimeException(__('Trashed file is missing.'));
        }

        $target = $this->rootAbsolutePath($entry->root).DIRECTORY_SEPARATOR.str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $this->normalizeRelativePath($entry->original_path),
        );

        if (file_exists($target)) {
            throw new RuntimeException(__('A file or folder already exists at the original path.'));
        }

        File::ensureDirectoryExists(dirname($target));

        if (! File::move($trashAbsolute, $target)) {
            throw new RuntimeException(__('Could not restore the item from trash.'));
        }

        $parent = dirname($trashAbsolute);

        if (is_dir($parent) && count(File::files($parent)) === 0 && count(File::directories($parent)) === 0) {
            File::deleteDirectory($parent);
        }
    }

    public function trashAbsolutePath(string $root, string $trashRelativePath): string
    {
        $this->assertRoot($root);

        $normalized = $this->normalizeRelativePath($trashRelativePath);

        return $this->rootAbsolutePath($root).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }

    public function trashDirname(): string
    {
        return (string) config('trash.file_trash_dirname', '.trash');
    }

    protected function isTrashDirectoryName(string $name): bool
    {
        return $name === $this->trashDirname();
    }

    public function resolveDownloadPath(string $root, string $relativePath): string
    {
        return $this->resolveFile($root, $relativePath);
    }

    public function assertRoot(string $root): void
    {
        if (! array_key_exists($root, self::ROOTS)) {
            throw new InvalidArgumentException(__('Unknown storage area.'));
        }
    }

    public function rootAbsolutePath(string $root): string
    {
        $this->assertRoot($root);

        return storage_path(self::ROOTS[$root]['path']);
    }

    public function ensureRootExists(string $root): void
    {
        $path = $this->rootAbsolutePath($root);

        if (! is_dir($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    protected function breadcrumbs(string $root, string $relativePath): array
    {
        $crumbs = [
            [
                'label' => self::ROOTS[$root]['label'],
                'path' => '',
            ],
        ];

        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '') {
            return $crumbs;
        }

        $parts = explode('/', $normalized);
        $built = [];

        foreach ($parts as $part) {
            $built[] = $part;
            $crumbs[] = [
                'label' => $part,
                'path' => implode('/', $built),
            ];
        }

        return $crumbs;
    }

    protected function parentPath(string $relativePath): ?string
    {
        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '') {
            return null;
        }

        $parent = dirname(str_replace('\\', '/', $normalized));

        return $parent === '.' ? '' : $parent;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function searchEntries(string $root, string $directory, string $search): array
    {
        $entries = [];
        $needle = mb_strtolower($search);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $limit = 250;
        $count = 0;

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $name = $item->getFilename();
            $pathname = $item->getPathname();

            if (str_contains($pathname, DIRECTORY_SEPARATOR.$this->trashDirname().DIRECTORY_SEPARATOR)
                || $name === $this->trashDirname()) {
                continue;
            }

            if (! str_contains(mb_strtolower($name), $needle)) {
                continue;
            }

            $entries[] = $this->mapEntry($root, $pathname, $item->isDir());
            $count++;

            if ($count >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return array{
     *     name: string,
     *     path: string,
     *     type: string,
     *     extension: string|null,
     *     size: int|null,
     *     mime: string|null,
     *     modified_at: string,
     *     items: int|null
     * }
     */
    protected function mapEntry(string $root, string $absolute, bool $isDirectory): array
    {
        $name = basename($absolute);
        $relative = $this->toRelative($root, $absolute);
        $mtime = @filemtime($absolute) ?: time();

        if ($isDirectory) {
            $childCount = 0;

            try {
                $childCount = count(File::files($absolute)) + count(File::directories($absolute));
            } catch (\Throwable) {
                $childCount = 0;
            }

            return [
                'name' => $name,
                'path' => $relative,
                'type' => 'folder',
                'extension' => null,
                'size' => null,
                'mime' => null,
                'modified_at' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
                'items' => $childCount,
            ];
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: null;

        return [
            'name' => $name,
            'path' => $relative,
            'type' => 'file',
            'extension' => $extension,
            'size' => @filesize($absolute) ?: 0,
            'mime' => @mime_content_type($absolute) ?: null,
            'modified_at' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
            'items' => null,
        ];
    }

    /**
     * @return array{file_count: int, folder_count: int, total_bytes: int}
     */
    protected function directoryStats(string $directory): array
    {
        $fileCount = 0;
        $folderCount = 0;
        $totalBytes = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                /** @var \SplFileInfo $item */
                if ($item->isDir()) {
                    $folderCount++;
                } else {
                    $fileCount++;
                    $totalBytes += $item->getSize();
                }
            }
        } catch (\Throwable) {
            // Empty or unreadable roots still render.
        }

        return [
            'file_count' => $fileCount,
            'folder_count' => $folderCount,
            'total_bytes' => $totalBytes,
        ];
    }

    protected function resolveDirectory(string $root, string $relativePath): string
    {
        $this->ensureRootExists($root);
        $rootPath = $this->rootAbsolutePath($root);
        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '') {
            return $rootPath;
        }

        $candidate = $rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $realRoot = realpath($rootPath);
        $realCandidate = realpath($candidate);

        if ($realRoot === false || $realCandidate === false || ! is_dir($realCandidate)) {
            throw new InvalidArgumentException(__('Folder not found.'));
        }

        if ($realCandidate !== $realRoot && ! str_starts_with($realCandidate, $realRoot.DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(__('Invalid folder path.'));
        }

        return $realCandidate;
    }

    protected function resolveFile(string $root, string $relativePath): string
    {
        $absolute = $this->resolveExisting($root, $relativePath);

        if (! is_file($absolute)) {
            throw new InvalidArgumentException(__('File not found.'));
        }

        return $absolute;
    }

    protected function resolveExisting(string $root, string $relativePath): string
    {
        $this->assertRoot($root);
        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '') {
            throw new InvalidArgumentException(__('A file or folder path is required.'));
        }

        $rootPath = $this->rootAbsolutePath($root);
        $candidate = $rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $realRoot = realpath($rootPath);
        $realCandidate = realpath($candidate);

        if ($realRoot === false || $realCandidate === false || (! is_file($realCandidate) && ! is_dir($realCandidate))) {
            throw new InvalidArgumentException(__('File or folder not found.'));
        }

        if ($realCandidate !== $realRoot && ! str_starts_with($realCandidate, $realRoot.DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(__('Invalid path.'));
        }

        return $realCandidate;
    }

    protected function toRelative(string $root, string $absolute): string
    {
        $rootPath = $this->rootAbsolutePath($root);
        $realRoot = realpath($rootPath) ?: $rootPath;
        $realAbsolute = realpath($absolute) ?: $absolute;

        $relative = Str::after($realAbsolute, rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);

        if ($relative === $realAbsolute) {
            return '';
        }

        return $this->normalizeRelativePath(str_replace(DIRECTORY_SEPARATOR, '/', $relative));
    }

    protected function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '' || $path === '.') {
            return '';
        }

        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidArgumentException(__('Invalid path.'));
            }

            if (preg_match('/[\x00-\x1F]/', $segment)) {
                throw new InvalidArgumentException(__('Invalid path.'));
            }

            $parts[] = $segment;
        }

        return implode('/', $parts);
    }

    protected function sanitizeName(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '', $name));

        if ($name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException(__('Enter a valid name.'));
        }

        if (preg_match('/[\x00-\x1F<>:"|?*]/', $name)) {
            throw new InvalidArgumentException(__('The name contains invalid characters.'));
        }

        if (mb_strlen($name) > 180) {
            throw new InvalidArgumentException(__('The name is too long.'));
        }

        return $name;
    }

    protected function uniqueName(string $directory, string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $i = 1;

        do {
            $candidate = $extension !== ''
                ? "{$base} ({$i}).{$extension}"
                : "{$base} ({$i})";
            $i++;
        } while (file_exists($directory.DIRECTORY_SEPARATOR.$candidate));

        return $candidate;
    }

    protected function publicUrl(string $relativePath): ?string
    {
        $normalized = $this->normalizeRelativePath($relativePath);

        if ($normalized === '') {
            return null;
        }

        return asset('storage/'.$normalized);
    }

    protected function isTextExtension(?string $extension): bool
    {
        return $extension !== null && in_array(strtolower($extension), self::TEXT_EXTENSIONS, true);
    }

    protected function isImageExtension(?string $extension): bool
    {
        return $extension !== null && in_array(strtolower($extension), self::IMAGE_EXTENSIONS, true);
    }
}
