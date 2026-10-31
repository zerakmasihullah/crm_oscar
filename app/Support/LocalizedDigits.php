<?php

namespace App\Support;

final class LocalizedDigits
{
    /** @var list<string> */
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** @var list<string> */
    private const EASTERN_ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function format(mixed $value, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $string = (string) $value;

        if (in_array($locale, ['fa', 'ps'], true)) {
            return preg_replace_callback(
                '/\d/',
                static fn (array $matches): string => self::FA[(int) $matches[0]],
                $string,
            ) ?? $string;
        }

        return $string;
    }

    public static function normalize(mixed $value): string
    {
        $string = (string) $value;

        $string = str_replace(self::FA, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $string);
        $string = str_replace(self::EASTERN_ARABIC, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $string);

        return $string;
    }

    public static function usesLocalizedDigits(?string $locale = null): bool
    {
        $locale ??= app()->getLocale();

        return in_array($locale, ['fa', 'ps'], true);
    }
}
