<?php

namespace App\Support;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Derive chat UI metadata from agent tool activity (no extra LLM tokens).
 */
class AiChatActivity
{
    /**
     * @return list<array{type: string, tool: string, label: string, summary: string, status: string, detail: string|null}>
     */
    public static function stepsFromResponse(AgentResponse $response): array
    {
        $steps = [];

        foreach ($response->toolResults ?? [] as $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }

            $detail = is_string($result->result)
                ? $result->result
                : (is_scalar($result->result) ? (string) $result->result : null);

            $status = 'done';
            if ($result->denied) {
                $status = 'denied';
            } elseif (is_string($detail) && str_starts_with($detail, 'Error:')) {
                $status = 'failed';
            }

            $steps[] = [
                'type' => 'tool',
                'tool' => $result->name,
                'label' => self::toolLabel($result->name),
                'summary' => self::summarize($result->name, $result->arguments),
                'status' => $status,
                'detail' => $detail !== null ? mb_substr(preg_replace('/\[[^\]]+\]\([^)]+\)/', '', $detail) ?? $detail, 0, 180) : null,
            ];
        }

        if ($steps === [] && ($response->toolCalls ?? collect())->isNotEmpty()) {
            foreach ($response->toolCalls as $call) {
                if (! $call instanceof ToolCall) {
                    continue;
                }

                $steps[] = [
                    'type' => 'tool',
                    'tool' => $call->name,
                    'label' => self::toolLabel($call->name),
                    'summary' => self::summarize($call->name, $call->arguments),
                    'status' => 'awaiting',
                    'detail' => null,
                ];
            }
        }

        foreach ($response->pendingApprovals ?? [] as $approval) {
            $arr = is_object($approval) && method_exists($approval, 'toArray')
                ? $approval->toArray()
                : (array) $approval;
            $tool = (string) ($arr['tool'] ?? 'DeleteRecord');
            $args = is_array($arr['arguments'] ?? null) ? $arr['arguments'] : [];

            $steps[] = [
                'type' => 'approval',
                'tool' => $tool,
                'label' => self::toolLabel($tool),
                'summary' => self::summarize($tool, $args),
                'status' => 'awaiting_approval',
                'detail' => is_string($arr['reason'] ?? null) ? $arr['reason'] : null,
            ];
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>|null  $toolCalls
     * @param  array<string, mixed>|null  $toolResults
     * @return list<array{type: string, tool: string, label: string, summary: string, status: string, detail: string|null}>
     */
    public static function stepsFromStored(?array $toolCalls, ?array $toolResults, array $pendingApprovals = []): array
    {
        $steps = [];

        foreach ($toolResults ?? [] as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }
            $detail = is_string($row['result'] ?? null) ? $row['result'] : null;
            $status = ! empty($row['denied'])
                ? 'denied'
                : (is_string($detail) && str_starts_with($detail, 'Error:') ? 'failed' : 'done');

            $steps[] = [
                'type' => 'tool',
                'tool' => (string) $row['name'],
                'label' => self::toolLabel((string) $row['name']),
                'summary' => self::summarize((string) $row['name'], is_array($row['arguments'] ?? null) ? $row['arguments'] : []),
                'status' => $status,
                'detail' => $detail !== null ? mb_substr($detail, 0, 180) : null,
            ];
        }

        if ($steps === []) {
            foreach ($toolCalls ?? [] as $row) {
                if (! is_array($row) || ! isset($row['name'])) {
                    continue;
                }
                $steps[] = [
                    'type' => 'tool',
                    'tool' => (string) $row['name'],
                    'label' => self::toolLabel((string) $row['name']),
                    'summary' => self::summarize((string) $row['name'], is_array($row['arguments'] ?? null) ? $row['arguments'] : []),
                    'status' => 'awaiting',
                    'detail' => null,
                ];
            }
        }

        foreach ($pendingApprovals as $approval) {
            if (! is_array($approval)) {
                continue;
            }
            $tool = (string) ($approval['tool'] ?? 'DeleteRecord');
            $args = is_array($approval['arguments'] ?? null) ? $approval['arguments'] : [];
            $steps[] = [
                'type' => 'approval',
                'tool' => $tool,
                'label' => self::toolLabel($tool),
                'summary' => self::summarize($tool, $args),
                'status' => 'awaiting_approval',
                'detail' => is_string($approval['reason'] ?? null) ? $approval['reason'] : null,
            ];
        }

        return $steps;
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    public static function linksFromResponse(AgentResponse $response): array
    {
        $links = [];

        foreach ($response->toolResults ?? [] as $result) {
            if (! $result instanceof ToolResult || ! is_string($result->result)) {
                continue;
            }
            foreach (self::extractMarkdownLinks($result->result) as $link) {
                $links[] = $link;
            }
        }

        foreach (self::extractMarkdownLinks((string) $response->text) as $link) {
            $links[] = $link;
        }

        return self::uniqueLinks($links);
    }

    /**
     * Successful create/update/delete tools that should refresh the open page.
     *
     * @return list<array{tool: string, resource: string|null, id: int|string|null}>
     */
    public static function mutationsFromResponse(AgentResponse $response): array
    {
        $mutations = [];
        $writeTools = ['CreateRecord', 'UpdateRecord', 'DeleteRecord'];

        foreach ($response->toolResults ?? [] as $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }

            if (! in_array($result->name, $writeTools, true)) {
                continue;
            }

            if ($result->denied) {
                continue;
            }

            $detail = is_string($result->result) ? $result->result : '';
            if ($detail !== '' && str_starts_with($detail, 'Error:')) {
                continue;
            }

            $args = is_array($result->arguments) ? $result->arguments : [];

            $mutations[] = [
                'tool' => $result->name,
                'resource' => isset($args['resource']) ? (string) $args['resource'] : null,
                'id' => $args['id'] ?? null,
            ];
        }

        return $mutations;
    }

    /**
     * Force markdown hrefs to app-relative paths so chat never sends users to a wrong host.
     */
    public static function sanitizeText(string $text): string
    {
        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            function (array $match): string {
                $label = $match[1];
                $href = self::toAppPath($match[2]);

                return '['.$label.']('.$href.')';
            },
            $text,
        );
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    public static function extractMarkdownLinks(string $text): array
    {
        $links = [];
        if (preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $href = self::toAppPath($match[2]);
                if ($href === '' || $href === '#') {
                    continue;
                }
                $links[] = [
                    'label' => $match[1],
                    'href' => $href,
                ];
            }
        }

        return $links;
    }

    public static function toAppPath(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $href;
        }

        if (preg_match('#^https?://[^/]+(/.*)?$#i', $href, $m) === 1) {
            $path = $m[1] ?? '/';

            return $path === '' ? '/' : $path;
        }

        // Bare paths without leading slash (e.g. field-reports/1/edit)
        if (preg_match('#^[a-z0-9][a-z0-9\-_/]*$#i', $href) === 1) {
            return '/'.$href;
        }

        return $href;
    }

    public static function toolLabel(string $tool): string
    {
        return match ($tool) {
            'ListResources' => 'List sections',
            'ListRecords' => 'List records',
            'GetRecord' => 'Get record',
            'CreateRecord' => 'Create record',
            'UpdateRecord' => 'Update record',
            'DeleteRecord' => 'Delete record',
            'ListPermissions' => 'List permissions',
            'RunAnalyticsQuery' => 'Run analytics',
            default => $tool,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function summarize(string $tool, array $arguments): string
    {
        $resource = isset($arguments['resource']) ? (string) $arguments['resource'] : null;
        $id = isset($arguments['id']) ? (string) $arguments['id'] : null;

        return match ($tool) {
            'ListResources' => 'Checking available sections',
            'ListRecords' => 'Listing '.($resource ?? 'records'),
            'GetRecord' => 'Opening '.($resource ?? 'record').($id ? " #{$id}" : ''),
            'CreateRecord' => 'Creating '.($resource ?? 'record'),
            'UpdateRecord' => 'Updating '.($resource ?? 'record').($id ? " #{$id}" : ''),
            'DeleteRecord' => 'Deleting '.($resource ?? 'record').($id ? " #{$id}" : ''),
            'ListPermissions' => 'Looking up permissions',
            'RunAnalyticsQuery' => 'Running read-only report',
            default => $tool,
        };
    }

    /**
     * @param  list<array{label: string, href: string}>  $links
     * @return list<array{label: string, href: string}>
     */
    public static function uniqueLinks(array $links): array
    {
        $seen = [];
        $out = [];
        foreach ($links as $link) {
            $key = $link['href'].'|'.$link['label'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $link;
        }

        return $out;
    }
}
