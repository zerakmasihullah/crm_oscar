<?php

namespace Tests\Unit;

use App\Support\LocalizedDigits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalizedDigitsTest extends TestCase
{
    #[Test]
    public function it_formats_farsi_and_pashto_digits(): void
    {
        $this->assertSame('۴۲', LocalizedDigits::format(42, 'fa'));
        $this->assertSame('۴۲', LocalizedDigits::format(42, 'ps'));
        $this->assertSame('42', LocalizedDigits::format(42, 'en'));
        // Unknown / removed locales keep western digits
        $this->assertSame('42', LocalizedDigits::format(42, 'ar'));
        $this->assertSame('42', LocalizedDigits::format(42, 'ur'));
    }

    #[Test]
    public function it_normalizes_eastern_digits_to_ascii(): void
    {
        $this->assertSame('42', LocalizedDigits::normalize('۴۲'));
        $this->assertSame('42', LocalizedDigits::normalize('٤٢'));
    }
}
