<?php

namespace App\Services\Ai;

/**
 * Rewrites common SQLite-only date idioms into MySQL (and leaves SQLite alone).
 */
class SqlDialectNormalizer
{
    public function normalize(string $sql, string $driver): string
    {
        $driver = strtolower($driver);

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $sql;
        }

        $out = $sql;

        // datetime('now') / date('now')
        $out = preg_replace("/\bDATETIME\s*\(\s*'now'\s*\)/i", 'NOW()', $out) ?? $out;
        $out = preg_replace("/\bDATE\s*\(\s*'now'\s*\)/i", 'CURDATE()', $out) ?? $out;

        // date('now', '-1 month') / datetime('now', '+7 days')
        $out = preg_replace_callback(
            "/\b(DATE|DATETIME)\s*\(\s*'now'\s*,\s*'([+-]?\d+)\s+(day|days|month|months|year|years|hour|hours|minute|minutes)'\s*\)/i",
            function (array $m): string {
                $fn = strtoupper($m[1]) === 'DATETIME' ? 'DATE_ADD' : 'DATE_ADD';
                $n = (int) $m[2];
                $unit = $this->mysqlIntervalUnit($m[3]);
                $base = strtoupper($m[1]) === 'DATETIME' ? 'NOW()' : 'CURDATE()';

                if ($n < 0) {
                    return 'DATE_SUB('.$base.', INTERVAL '.abs($n).' '.$unit.')';
                }

                return $fn.'('.$base.', INTERVAL '.$n.' '.$unit.')';
            },
            $out,
        ) ?? $out;

        // julianday / strftime leftovers → leave; author should avoid them

        return $out;
    }

    protected function mysqlIntervalUnit(string $unit): string
    {
        return match (strtolower($unit)) {
            'day', 'days' => 'DAY',
            'month', 'months' => 'MONTH',
            'year', 'years' => 'YEAR',
            'hour', 'hours' => 'HOUR',
            'minute', 'minutes' => 'MINUTE',
            default => 'DAY',
        };
    }
}
