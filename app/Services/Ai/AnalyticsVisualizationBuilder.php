<?php

namespace App\Services\Ai;

/**
 * Maps tabular SQL results into KPI / chart / table section payloads for the UI.
 */
class AnalyticsVisualizationBuilder
{
    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     type: 'kpi'|'chart'|'table',
     *     title: string,
     *     chart_type?: 'bar'|'line'|'pie',
     *     kpi?: array{label: string, value: string, hint: string|null},
     *     labels?: list<string>,
     *     datasets?: list<array{label: string, data: list<float|int>}>,
     *     columns?: list<string>,
     *     rows?: list<array<string, mixed>>
     * }
     */
    public function build(string $title, string $viz, array $columns, array $rows): array
    {
        $viz = in_array($viz, ['kpi', 'bar', 'line', 'pie', 'table'], true) ? $viz : 'table';

        if ($rows === [] || $columns === []) {
            return [
                'type' => 'table',
                'title' => $title,
                'columns' => $columns,
                'rows' => [],
                'truncated' => false,
                'total_rows' => 0,
            ];
        }

        if ($viz === 'kpi' || $this->looksLikeSingleMetric($columns, $rows)) {
            return $this->asKpi($title, $columns, $rows);
        }

        if (in_array($viz, ['bar', 'line', 'pie'], true)) {
            $chart = $this->asChart($title, $viz, $columns, $rows);
            if ($chart !== null) {
                return $chart;
            }
        }

        if ($viz === 'table') {
            return [
                'type' => 'table',
                'title' => $title,
                'columns' => $columns,
                'rows' => array_slice($rows, 0, 100),
                'truncated' => count($rows) > 100,
                'total_rows' => count($rows),
            ];
        }

        $chart = $this->asChart($title, 'bar', $columns, $rows);
        if ($chart !== null) {
            return $chart;
        }

        return [
            'type' => 'table',
            'title' => $title,
            'columns' => $columns,
            'rows' => array_slice($rows, 0, 100),
            'truncated' => count($rows) > 100,
            'total_rows' => count($rows),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    protected function looksLikeSingleMetric(array $columns, array $rows): bool
    {
        return count($rows) === 1 && count($columns) <= 2 && $this->numericColumns($columns, $rows) !== [];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{type: 'kpi', title: string, kpi: array{label: string, value: string, hint: string|null}}
     */
    protected function asKpi(string $title, array $columns, array $rows): array
    {
        $row = $rows[0];
        $numeric = $this->numericColumns($columns, $rows);
        $valueCol = $numeric[0] ?? $columns[0];
        $labelCol = null;

        foreach ($columns as $col) {
            if ($col !== $valueCol) {
                $labelCol = $col;
                break;
            }
        }

        $value = $row[$valueCol] ?? 0;
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        return [
            'type' => 'kpi',
            'title' => $title,
            'kpi' => [
                'label' => $title,
                'value' => $this->formatNumber($value),
                'hint' => $labelCol !== null ? (string) ($row[$labelCol] ?? '') : null,
            ],
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array{type: 'chart', title: string, chart_type: 'bar'|'line'|'pie', labels: list<string>, datasets: list<array{label: string, data: list<float|int>}}|null
     */
    protected function asChart(string $title, string $chartType, array $columns, array $rows): ?array
    {
        $numeric = $this->numericColumns($columns, $rows);
        if ($numeric === []) {
            return null;
        }

        $labelCol = null;
        foreach ($columns as $col) {
            if (! in_array($col, $numeric, true)) {
                $labelCol = $col;
                break;
            }
        }

        if ($labelCol === null) {
            $labelCol = $columns[0];
            $numeric = array_values(array_filter($numeric, fn (string $c) => $c !== $labelCol));
            if ($numeric === []) {
                return null;
            }
        }

        $slice = array_slice($rows, 0, 36);
        $labels = [];
        foreach ($slice as $i => $row) {
            $raw = $row[$labelCol] ?? ('#'.($i + 1));
            if (is_bool($raw)) {
                $raw = $raw ? 'true' : 'false';
            }
            if ($raw instanceof \DateTimeInterface) {
                $raw = $raw->format('Y-m-d');
            }
            $labels[] = mb_substr((string) $raw, 0, 48);
        }

        $datasets = [];
        foreach (array_slice($numeric, 0, 3) as $col) {
            $data = [];
            foreach ($slice as $row) {
                $data[] = $this->toFloat($row[$col] ?? 0);
            }
            $datasets[] = [
                'label' => $this->humanizeColumn($col),
                'data' => $data,
            ];
        }

        if ($chartType === 'pie') {
            $first = array_shift($datasets);
            if ($first === null) {
                return null;
            }
            $datasets = [$first];
            // Keep pie readable: top categories only
            if (count($labels) > 8) {
                $paired = [];
                foreach ($labels as $i => $label) {
                    $paired[] = ['label' => $label, 'value' => $datasets[0]['data'][$i] ?? 0];
                }
                usort($paired, fn ($a, $b) => $b['value'] <=> $a['value']);
                $top = array_slice($paired, 0, 7);
                $other = array_slice($paired, 7);
                $otherSum = array_sum(array_column($other, 'value'));
                if ($otherSum > 0) {
                    $top[] = ['label' => 'Other', 'value' => $otherSum];
                }
                $labels = array_column($top, 'label');
                $datasets[0]['data'] = array_map(fn ($p) => (float) $p['value'], $top);
            }
        }

        return [
            'type' => 'chart',
            'title' => $title,
            'chart_type' => $chartType === 'line' ? 'line' : ($chartType === 'pie' ? 'pie' : 'bar'),
            'labels' => $labels,
            'datasets' => $datasets,
            'point_count' => count($labels),
            'source_rows' => count($rows),
        ];
    }

    protected function humanizeColumn(string $col): string
    {
        return ucwords(str_replace('_', ' ', $col));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    protected function numericColumns(array $columns, array $rows): array
    {
        $sample = array_slice($rows, 0, 20);
        $out = [];

        foreach ($columns as $col) {
            $numericCount = 0;
            $seen = 0;
            foreach ($sample as $row) {
                if (! array_key_exists($col, $row) || $row[$col] === null || $row[$col] === '') {
                    continue;
                }
                $seen++;
                if (is_numeric($row[$col])) {
                    $numericCount++;
                }
            }
            if ($seen > 0 && $numericCount === $seen) {
                $out[] = $col;
            }
        }

        return $out;
    }

    protected function toFloat(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected function formatNumber(mixed $value): string
    {
        if (is_string($value) && ! is_numeric($value)) {
            return $value;
        }

        $num = is_numeric($value) ? (float) $value : 0.0;
        if (abs($num - round($num)) < 0.00001) {
            return number_format((int) round($num));
        }

        return number_format($num, 2);
    }
}
