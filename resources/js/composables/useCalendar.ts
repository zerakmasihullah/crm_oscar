import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    type CalendarSystem,
    type DateParts,
    calendarPartsToGregorianString,
    formatDate,
    formatDateTime,
    gregorianStringToCalendarParts,
    monthNames,
    todayGregorianString,
    toCalendarParts,
    todayGregorianParts,
} from '@/lib/calendar';
import { localizeDigits, normalizeDigits } from '@/lib/localized-digits';

function resolveSystem(value: unknown): CalendarSystem {
    if (value === 'gregorian' || value === 'jalali' || value === 'hijri') {
        return value;
    }
    return 'jalali';
}

export function useCalendar(forcedSystem?: CalendarSystem) {
    const page = usePage();

    const locale = computed(
        () => (page.props.locale as string | undefined) ?? 'en',
    );

    const system = computed<CalendarSystem>(() => {
        if (forcedSystem) return forcedSystem;
        return resolveSystem(page.props.calendarSystem);
    });

    const todayParts = computed(() => {
        const g = todayGregorianParts();
        return toCalendarParts(g, system.value);
    });

    const todayString = computed(() => {
        const parts = todayParts.value;
        if (!parts) return '';
        return formatDate(
            todayGregorianString(),
            system.value,
            locale.value,
        );
    });

    function format(
        value: Date | string | null | undefined,
        options: { includeMonthName?: boolean; separator?: string } = {},
    ): string {
        return formatDate(value, system.value, locale.value, options);
    }

    function formatLong(value: Date | string | null | undefined): string {
        return formatDate(value, system.value, locale.value, {
            includeMonthName: true,
        });
    }

    function formatDateTimeValue(
        value: Date | string | null | undefined,
    ): string {
        return formatDateTime(value, system.value, locale.value);
    }

    function toParts(
        value: Date | string | null | undefined,
    ): DateParts | null {
        return gregorianStringToCalendarParts(value, system.value);
    }

    function toGregorianString(parts: DateParts): string {
        return calendarPartsToGregorianString(parts, system.value);
    }

    function digits(value: string | number): string {
        return localizeDigits(value, locale.value);
    }

    function months(): string[] {
        return monthNames(system.value, locale.value);
    }

    return {
        locale,
        system,
        todayParts,
        todayString,
        todayGregorian: todayGregorianString,
        format,
        formatLong,
        formatDateTime: formatDateTimeValue,
        toParts,
        toGregorianString,
        digits,
        months,
        localizeDigits: digits,
        normalizeDigits,
    };
}
