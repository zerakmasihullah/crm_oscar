<?php

namespace App\Support;

use App\Models\OrgSetting;
use Carbon\CarbonInterface;
use DateTimeInterface;
use IntlDateFormatter;

class CalendarSystem
{
    public const GREGORIAN = 'gregorian';

    public const JALALI = 'jalali';

    public const HIJRI = 'hijri';

    /**
     * @return list<string>
     */
    public static function options(): array
    {
        return [self::GREGORIAN, self::JALALI, self::HIJRI];
    }

    public static function current(): string
    {
        $value = (string) OrgSetting::getValue('calendar_system', self::JALALI);

        return in_array($value, self::options(), true) ? $value : self::JALALI;
    }

    /**
     * Format a date for display using the org calendar preference.
     *
     * Uses ICU calendars: Gregorian, Persian (Jalali/Shamsi), and Islamic Umm al-Qura.
     */
    public static function format(
        DateTimeInterface|string|null $date,
        ?string $system = null,
        ?string $locale = null,
        string $pattern = 'yyyy/MM/dd',
    ): string {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? \Illuminate\Support\Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            : \Illuminate\Support\Carbon::parse($date);

        $system ??= self::current();
        $locale ??= app()->getLocale();
        $calendar = match ($system) {
            self::JALALI => 'persian',
            self::HIJRI => 'islamic-umalqura',
            default => 'gregorian',
        };

        if (! class_exists(IntlDateFormatter::class)) {
            return $carbon->format('Y-m-d');
        }

        $formatter = new IntlDateFormatter(
            self::icuLocale($locale, $calendar),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $carbon->timezoneName,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        $formatted = $formatter->format($carbon);

        return is_string($formatted) ? $formatted : $carbon->format('Y-m-d');
    }

    public static function formatDateTime(
        DateTimeInterface|string|null $date,
        ?string $system = null,
        ?string $locale = null,
    ): string {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof CarbonInterface
            ? $date
            : \Illuminate\Support\Carbon::parse($date);

        $datePart = self::format($carbon, $system, $locale, 'yyyy/MM/dd');
        $timePart = $carbon->format('H:i');

        return trim("{$datePart} {$timePart}");
    }

    protected static function icuLocale(string $locale, string $calendar): string
    {
        $base = match ($locale) {
            'fa', 'ps' => $locale.'-AF',
            default => 'en',
        };

        return "{$base}-u-ca-{$calendar}";
    }
}
