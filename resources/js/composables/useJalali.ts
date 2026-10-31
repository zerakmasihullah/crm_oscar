import { toJalaali, toGregorian, jalaaliMonthLength } from 'jalaali-js';
import { localizeDigits, normalizeDigits } from '@/lib/localized-digits';

export type JalaliParts = {
    jy: number;
    jm: number;
    jd: number;
};

/**
 * @deprecated Prefer `useCalendar()` which respects the org calendar setting.
 * Kept for Jalali-specific helpers and backward compatibility.
 */
export function useJalali(locale = 'fa') {
    function fromGregorian(date: Date | string): JalaliParts {
        const d = typeof date === 'string' ? new Date(date) : date;

        return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
    }

    function toGregorianDate({ jy, jm, jd }: JalaliParts): Date {
        const g = toGregorian(jy, jm, jd);

        return new Date(g.gy, g.gm - 1, g.gd);
    }

    function format(date: Date | string, pattern = 'yyyy/mm/dd'): string {
        const { jy, jm, jd } = fromGregorian(date);
        const raw = pattern
            .replace('yyyy', String(jy))
            .replace('mm', String(jm).padStart(2, '0'))
            .replace('dd', String(jd).padStart(2, '0'));

        return localizeDigits(raw, locale);
    }

    function monthLength(jy: number, jm: number): number {
        return jalaaliMonthLength(jy, jm);
    }

    return {
        fromGregorian,
        toGregorianDate,
        format,
        monthLength,
        localizeDigits: (value: string | number) => localizeDigits(value, locale),
        normalizeDigits,
    };
}
