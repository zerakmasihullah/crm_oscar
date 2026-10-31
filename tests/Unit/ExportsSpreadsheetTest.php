<?php

namespace Tests\Unit;

use App\Concerns\ExportsSpreadsheet;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportsSpreadsheetTest extends TestCase
{
    #[Test]
    public function it_builds_csv_and_excel_filenames(): void
    {
        $exporter = new class
        {
            use ExportsSpreadsheet;

            public function name(string $basename, string $extension): string
            {
                return $this->exportFilename($basename, $extension);
            }
        };

        $this->assertSame('users.csv', $exporter->name('users', 'csv'));
        $this->assertSame('users.xls', $exporter->name('users.csv', 'xls'));
        $this->assertSame('roles.xls', $exporter->name('roles.xlsx', 'xls'));
    }
}
