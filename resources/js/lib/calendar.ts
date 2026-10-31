import { gregorianToHijri, hijriToGregorian } from '@tabby_ai/hijri-converter';
import {
    isValidJalaaliDate,
    jalaaliMonthLength,
    toGregorian,
    toJalaali,
} from 'jalaali-js';
import { localizeDigits, normalizeDigits } from '@/lib/localized-digits';

export type CalendarSystem = 'gregorian' | 'jalali' | 'hijri';

export type DateParts = {
    year: number;
    month: number;
    day: number;
};

export const CALENDAR_SYSTEMS: CalendarSystem[] = [
    'gregorian',
    'jalali',
    'hijri',
];

/** Afghan solar (Jalali / Hijri Shamsi) month names. */
export const JALALI_MONTH_NAMES: Record<string, string[]> = {
    en: [
        'Hamal',
        'Sawr',
        'Jawza',
        'Saratan',
        'Asad',
        'Sonbola',
        'Mizan',
        'Aqrab',
        'Qaws',
        'Jadi',
        'Dalw',
        'Hut',
    ],
    fa: [
        'حمل',
        'ثور',
        'جوزا',
        'سرطان',
        'اسد',
        'سنبله',
        'میزان',
        'عقرب',
        'قوس',
        'جدی',
        'دلو',
        'حوت',
    ],
    ps: [
        'وری',
        'غویی',
        'غبرګولی',
        'چنګاښ',
        'زمری',
        'وږی',
        'تله',
        'لړم',
        'لیندۍ',
        'مرغومی',
        'سلواغه',
        'کب',
    ],
};

/** Lunar Hijri (Umm al-Qura) month names. */
export const HIJRI_MONTH_NAMES: Record<string, string[]> = {
    en: [
        'Muharram',
        'Safar',
        "Rabi' I",
        "Rabi' II",
        'Jumada I',
        'Jumada II',
        'Rajab',
        "Sha'ban",
        'Ramadan',
        'Shawwal',
        "Dhul-Qa'dah",
        'Dhul-Hijjah',
    ],
    fa: [
        'محرم',
        'صفر',
        'ربیع‌الاول',
        'ربیع‌الثانی',
        'جمادی‌الاول',
        'جمادی‌الثانی',
        'رجب',
        'شعبان',
        'رمضان',
        'شوال',
        'ذوالقعده',
        'ذوالحجه',
    ],
    ps: [
        'محرم',
        'صفر',
        'ربیع الاول',
        'ربیع الثانی',
        'جمادی الاول',
        'جمادی الثانی',
        'رجب',
        'شعبان',
        'رمضان',
        'شوال',
        'ذوالقعده',
        'ذوالحجه',
    ],
};

export const GREGORIAN_MONTH_NAMES: Record<string, string[]> = {
    en: [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ],
    fa: [
        'ژانویه',
        'فوریه',
        'مارس',
        'آوریل',
        'مه',
        'ژوئن',
        'ژوئیه',
        'اوت',
        'سپتامبر',
        'اکتبر',
        'نوامبر',
        'دسامبر',
    ],
    ps: [
        'جنوري',
        'فبروري',
        'مارچ',
        'اپریل',
        'می',
        'جون',
        'جولای',
        'اګست',
        'سپتمبر',
        'اکتوبر',
        'نومبر',
        'دسمبر',
    ],
};

/** Week starts Saturday in Afghanistan. */
export const WEEKDAY_NAMES_SHORT: Record<string, string[]> = {
    en: ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr'],
    fa: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],
    ps: ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'],
};

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

function localeKey(locale: string): string {
    if (locale.startsWith('fa')) return 'fa';
    if (locale.startsWith('ps')) return 'ps';
    return 'en';
}

export function parseGregorianParts(
    value: Date | string | null | undefined,
): DateParts | null {
    if (value == null || value === '') return null;

    if (value instanceof Date) {
        if (Number.isNaN(value.getTime())) return null;
        return {
            year: value.getFullYear(),
            month: value.getMonth() + 1,
            day: value.getDate(),
        };
    }

    const str = normalizeDigits(value).trim();
    const match = str.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (!year || !month || !day) return null;

    const probe = new Date(year, month - 1, day);
    if (
        probe.getFullYear() !== year ||
        probe.getMonth() !== month - 1 ||
        probe.getDate() !== day
    ) {
        return null;
    }

    return { year, month, day };
}

export function gregorianPartsToString(parts: DateParts): string {
    return `${parts.year}-${pad2(parts.month)}-${pad2(parts.day)}`;
}

export function todayGregorianParts(timezone?: string | null): DateParts {
    const now = localizedNow(timezone);
    return {
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        day: now.getDate(),
    };
}

export function todayGregorianString(timezone?: string | null): string {
    return gregorianPartsToString(todayGregorianParts(timezone));
}

function localizedNow(timezone?: string | null): Date {
    if (!timezone) return new Date();

    try {
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
        const parts = formatter.formatToParts(new Date());
        const year = Number(parts.find((p) => p.type === 'year')?.value);
        const month = Number(parts.find((p) => p.type === 'month')?.value);
        const day = Number(parts.find((p) => p.type === 'day')?.value);
        return new Date(year, month - 1, day);
    } catch {
        return new Date();
    }
}

export function toCalendarParts(
    gregorian: DateParts,
    system: CalendarSystem,
): DateParts | null {
    try {
        if (system === 'jalali') {
            const j = toJalaali(gregorian.year, gregorian.month, gregorian.day);
            return { year: j.jy, month: j.jm, day: j.jd };
        }

        if (system === 'hijri') {
            const h = gregorianToHijri({
                year: gregorian.year,
                month: gregorian.month,
                day: gregorian.day,
            });
            return { year: h.year, month: h.month, day: h.day };
        }

        return { ...gregorian };
    } catch {
        return null;
    }
}

export function fromCalendarParts(
    parts: DateParts,
    system: CalendarSystem,
): DateParts | null {
    try {
        if (system === 'jalali') {
            if (!isValidJalaaliDate(parts.year, parts.month, parts.day)) {
                return null;
            }
            const g = toGregorian(parts.year, parts.month, parts.day);
            return { year: g.gy, month: g.gm, day: g.gd };
        }

        if (system === 'hijri') {
            const g = hijriToGregorian({
                year: parts.year,
                month: parts.month,
                day: parts.day,
            });
            return { year: g.year, month: g.month, day: g.day };
        }

        const probe = new Date(parts.year, parts.month - 1, parts.day);
        if (
            probe.getFullYear() !== parts.year ||
            probe.getMonth() !== parts.month - 1 ||
            probe.getDate() !== parts.day
        ) {
            return null;
        }

        return { ...parts };
    } catch {
        return null;
    }
}

export function gregorianStringToCalendarParts(
    value: Date | string | null | undefined,
    system: CalendarSystem,
): DateParts | null {
    const g = parseGregorianParts(value);
    if (!g) return null;
    return toCalendarParts(g, system);
}

export function calendarPartsToGregorianString(
    parts: DateParts,
    system: CalendarSystem,
): string {
    const g = fromCalendarParts(parts, system);
    return g ? gregorianPartsToString(g) : '';
}

export function monthLength(
    year: number,
    month: number,
    system: CalendarSystem,
): number {
    if (system === 'jalali') {
        return jalaaliMonthLength(year, month);
    }

    if (system === 'hijri') {
        try {
            hijriToGregorian({ year, month, day: 30 });
            return 30;
        } catch {
            return 29;
        }
    }

    return new Date(year, month, 0).getDate();
}

export function monthNames(
    system: CalendarSystem,
    locale = 'en',
): string[] {
    const key = localeKey(locale);
    if (system === 'jalali') {
        return JALALI_MONTH_NAMES[key] ?? JALALI_MONTH_NAMES.en;
    }
    if (system === 'hijri') {
        return HIJRI_MONTH_NAMES[key] ?? HIJRI_MONTH_NAMES.en;
    }
    return GREGORIAN_MONTH_NAMES[key] ?? GREGORIAN_MONTH_NAMES.en;
}

export function weekdayLabels(locale = 'en'): string[] {
    return WEEKDAY_NAMES_SHORT[localeKey(locale)] ?? WEEKDAY_NAMES_SHORT.en;
}

/** JS getDay() (0=Sun) → column index when week starts Saturday. */
export function weekdayColumn(jsWeekday: number): number {
    return (jsWeekday + 1) % 7;
}

export function firstWeekdayColumn(
    year: number,
    month: number,
    system: CalendarSystem,
): number {
    const g = fromCalendarParts({ year, month, day: 1 }, system);
    if (!g) return 0;
    return weekdayColumn(new Date(g.year, g.month - 1, g.day).getDay());
}

export function formatCalendarParts(
    parts: DateParts,
    system: CalendarSystem,
    locale = 'en',
    options: { includeMonthName?: boolean; separator?: string } = {},
): string {
    const sep = options.separator ?? '/';
    if (options.includeMonthName) {
        const names = monthNames(system, locale);
        const label = `${parts.day} ${names[parts.month - 1] ?? ''} ${parts.year}`;
        return localizeDigits(label, locale);
    }

    const raw = `${parts.year}${sep}${pad2(parts.month)}${sep}${pad2(parts.day)}`;
    return localizeDigits(raw, locale);
}

export function formatDate(
    value: Date | string | null | undefined,
    system: CalendarSystem,
    locale = 'en',
    options: { includeMonthName?: boolean; separator?: string } = {},
): string {
    const parts = gregorianStringToCalendarParts(value, system);
    if (!parts) return '';
    return formatCalendarParts(parts, system, locale, options);
}

export function formatDateTime(
    value: Date | string | null | undefined,
    system: CalendarSystem,
    locale = 'en',
): string {
    if (value == null || value === '') return '';

    const date =
        value instanceof Date
            ? value
            : new Date(normalizeDigits(String(value)).replace(' ', 'T'));

    if (Number.isNaN(date.getTime())) return String(value);

    const datePart = formatDate(date, system, locale);
    const timePart = localizeDigits(
        `${pad2(date.getHours())}:${pad2(date.getMinutes())}`,
        locale,
    );

    return `${datePart} ${timePart}`;
}

export function yearRange(
    system: CalendarSystem,
    center?: number,
): number[] {
    const today = toCalendarParts(todayGregorianParts(), system);
    const mid = center ?? today?.year ?? new Date().getFullYear();
    const list: number[] = [];
    const before = system === 'gregorian' ? 100 : 80;
    const after = system === 'gregorian' ? 20 : 20;

    for (let y = mid - before; y <= mid + after; y += 1) {
        list.push(y);
    }

    return list;
}

export function compareParts(a: DateParts, b: DateParts): number {
    if (a.year !== b.year) return a.year - b.year;
    if (a.month !== b.month) return a.month - b.month;
    return a.day - b.day;
}
