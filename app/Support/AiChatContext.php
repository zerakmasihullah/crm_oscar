<?php

namespace App\Support;

/**
 * Normalize and format General Chat page context (Cursor-style open page).
 */
class AiChatContext
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{label: string, path: string, resource: string|null, record_id: int|null}|null
     */
    public static function normalize(array $raw): ?array
    {
        $path = trim((string) ($raw['path'] ?? ''));
        $label = trim((string) ($raw['label'] ?? ''));

        if ($path === '' || $label === '') {
            return null;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $resource = isset($raw['resource']) ? trim((string) $raw['resource']) : null;
        if ($resource === '') {
            $resource = null;
        }

        $recordId = null;
        if (isset($raw['record_id']) && is_numeric($raw['record_id'])) {
            $id = (int) $raw['record_id'];
            $recordId = $id > 0 ? $id : null;
        }

        return [
            'label' => mb_substr($label, 0, 120),
            'path' => mb_substr($path, 0, 240),
            'resource' => $resource !== null ? mb_substr($resource, 0, 64) : null,
            'record_id' => $recordId,
        ];
    }

    /**
     * @param  array{label: string, path: string, resource: string|null, record_id: int|null}  $context
     */
    public static function promptBlock(array $context): string
    {
        $lines = [
            'Current UI context (what the user is looking at in the app):',
            '- Label: '.$context['label'],
            '- Path: '.$context['path'],
        ];

        if (filled($context['resource'])) {
            $lines[] = '- Catalog resource: '.$context['resource'];
        }

        if ($context['record_id'] !== null) {
            $lines[] = '- Record id: '.$context['record_id'];
        }

        $lines[] = 'When the user says "this", "here", "it", or refers to the current page without an id, prefer this context. Still call tools — do not invent data.';

        return implode("\n", $lines);
    }
}
