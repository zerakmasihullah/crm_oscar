<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * Ensures analytics SQL is a single read-only SELECT (or WITH … SELECT).
 */
class SqlGuard
{
    /**
     * @return string Normalized SQL ending with LIMIT when missing
     */
    public function sanitize(string $sql, int $maxRows = 200): string
    {
        $sql = trim($sql);
        $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql) ?? $sql;
        $sql = preg_replace('/\s*```$/', '', $sql) ?? $sql;
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r");

        if ($sql === '') {
            throw new InvalidArgumentException('SQL is empty.');
        }

        if (str_contains($sql, ';')) {
            throw new InvalidArgumentException('Multiple SQL statements are not allowed.');
        }

        // Strip block and line comments
        $stripped = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
        $stripped = preg_replace('/--.*?$/m', ' ', $stripped) ?? $stripped;
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);

        $upper = strtoupper($stripped);

        if (! preg_match('/^(WITH|SELECT)\b/', $upper)) {
            throw new InvalidArgumentException('Only SELECT / WITH…SELECT queries are allowed.');
        }

        // Whole-token matches only — never substring-match identifiers like created_at / updated_at.
        $forbiddenWords = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
            'REPLACE', 'MERGE', 'GRANT', 'REVOKE', 'ATTACH', 'DETACH', 'PRAGMA',
        ];

        foreach ($forbiddenWords as $token) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/', $upper) === 1) {
                throw new InvalidArgumentException("Forbidden SQL keyword or function: {$token}");
            }
        }

        $forbiddenPhrases = [
            'INTO OUTFILE',
            'INTO DUMPFILE',
            'LOAD_FILE',
            'SLEEP(',
            'BENCHMARK(',
            'PG_SLEEP',
            'COPY ',
            'CALL ',
            'EXECUTE ',
            'EXEC ',
        ];

        foreach ($forbiddenPhrases as $token) {
            if (str_contains($upper, $token)) {
                throw new InvalidArgumentException('Forbidden SQL keyword or function: '.trim($token));
            }
        }

        if (preg_match('/\bINTO\b/', $upper) === 1) {
            throw new InvalidArgumentException('INTO is not allowed in analytics SQL.');
        }

        if (! preg_match('/\bLIMIT\s+\d+/i', $stripped)) {
            $stripped .= ' LIMIT '.max(1, $maxRows);
        } else {
            $stripped = preg_replace_callback(
                '/\bLIMIT\s+(\d+)/i',
                function (array $m) use ($maxRows): string {
                    $n = min((int) $m[1], $maxRows);

                    return 'LIMIT '.$n;
                },
                $stripped,
            ) ?? $stripped;
        }

        return $stripped;
    }

    /**
     * @param  list<string>  $deniedTables
     */
    public function assertNoDeniedTables(string $sql, array $deniedTables): void
    {
        $upper = strtoupper($sql);
        foreach ($deniedTables as $table) {
            $table = trim($table);
            if ($table === '') {
                continue;
            }
            $pattern = '/\b'.preg_quote(strtoupper($table), '/').'\b/';
            if (preg_match($pattern, $upper) === 1) {
                throw new InvalidArgumentException("Access to table [{$table}] is not allowed for analytics.");
            }
        }
    }

    /**
     * Reject SELECT lists that reference deny columns (including aliases / qualified names).
     *
     * @param  list<string>  $deniedColumns
     */
    public function assertNoDeniedColumns(string $sql, array $deniedColumns): void
    {
        if ($deniedColumns === []) {
            return;
        }

        $stripped = preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
        $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $stripped) ?? $stripped;

        foreach ($deniedColumns as $column) {
            $column = trim((string) $column);
            if ($column === '') {
                continue;
            }

            $pattern = '/\b(?:[a-zA-Z0-9_]+\.)?'.preg_quote($column, '/').'\b/i';
            if (preg_match($pattern, $stripped) === 1) {
                throw new InvalidArgumentException(
                    "Column [{$column}] is not allowed in analytics queries.",
                );
            }
        }
    }
}
