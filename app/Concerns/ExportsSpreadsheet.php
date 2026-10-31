<?php

namespace App\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsSpreadsheet
{
    use ExportsCsv;

    /**
     * Stream a CSV or Excel-compatible SpreadsheetML download.
     *
     * @param  iterable<int, array<int|string, mixed>>  $rows
     * @param  list<string>  $headers
     */
    protected function streamExport(
        string $basename,
        array $headers,
        iterable $rows,
        string $format = 'csv',
    ): StreamedResponse {
        $format = strtolower($format);

        if (in_array($format, ['xlsx', 'xls', 'excel'], true)) {
            abort_unless(config('kit.features.excel_exports'), 404);

            return $this->streamExcelXml(
                $this->exportFilename($basename, 'xls'),
                $headers,
                $rows,
            );
        }

        return $this->streamCsv(
            $this->exportFilename($basename, 'csv'),
            $headers,
            $rows,
        );
    }

    /**
     * Excel-friendly SpreadsheetML (opens in Excel / LibreOffice without packages).
     *
     * @param  iterable<int, array<int|string, mixed>>  $rows
     * @param  list<string>  $headers
     */
    protected function streamExcelXml(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
            echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Export"><Table>';

            echo '<Row>';
            foreach ($headers as $header) {
                echo '<Cell><Data ss:Type="String">'.$this->excelXmlEscape((string) $header).'</Data></Cell>';
            }
            echo '</Row>';

            foreach ($rows as $row) {
                echo '<Row>';
                foreach (array_values($row) as $value) {
                    [$type, $text] = $this->excelXmlCell($value);
                    echo '<Cell><Data ss:Type="'.$type.'">'.$this->excelXmlEscape($text).'</Data></Cell>';
                }
                echo '</Row>';
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    protected function exportFilename(string $basename, string $extension): string
    {
        $basename = preg_replace('/\.(csv|xls|xlsx)$/i', '', $basename) ?: $basename;

        return $basename.'.'.$extension;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function excelXmlCell(mixed $value): array
    {
        if (is_int($value) || is_float($value)) {
            return ['Number', (string) $value];
        }

        if (is_bool($value)) {
            return ['String', $value ? '1' : '0'];
        }

        if ($value === null) {
            return ['String', ''];
        }

        return ['String', (string) $value];
    }

    private function excelXmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
