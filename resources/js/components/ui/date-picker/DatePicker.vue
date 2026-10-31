<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import {
    type CalendarSystem,
    type DateParts,
    calendarPartsToGregorianString,
    compareParts,
    firstWeekdayColumn,
    formatCalendarParts,
    formatDate,
    gregorianStringToCalendarParts,
    monthLength,
    monthNames,
    todayGregorianParts,
    todayGregorianString,
    toCalendarParts,
    weekdayLabels,
    yearRange,
} from '@/lib/calendar';
import { localizeDigits } from '@/lib/localized-digits';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        id?: string;
        disabled?: boolean;
        required?: boolean;
        placeholder?: string;
        min?: string | null;
        max?: string | null;
        class?: string;
        /** Force a calendar; otherwise uses org setting. */
        calendar?: CalendarSystem;
    }>(),
    {
        modelValue: '',
        disabled: false,
        required: false,
        placeholder: '',
        min: null,
        max: null,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useTranslations();
const page = usePage();

const open = ref(false);
const rootEl = ref<HTMLElement | null>(null);
const triggerEl = ref<HTMLButtonElement | null>(null);

const locale = computed(() => (page.props.locale as string) ?? 'en');
const rtl = computed(() => (page.props.dir as string) === 'rtl');

const system = computed<CalendarSystem>(() => {
    if (props.calendar) return props.calendar;
    const shared = page.props.calendarSystem;
    if (shared === 'gregorian' || shared === 'jalali' || shared === 'hijri') {
        return shared;
    }
    return 'jalali';
});

const systemLabel = computed(() => {
    if (system.value === 'jalali') return t('Jalali (Shamsi)');
    if (system.value === 'hijri') return t('Hijri (Lunar)');
    return t('Gregorian');
});

function safeParts(value: string | null | undefined): DateParts | null {
    return gregorianStringToCalendarParts(value, system.value);
}

const today = computed(
    () =>
        toCalendarParts(todayGregorianParts(), system.value) ?? {
            year: 1400,
            month: 1,
            day: 1,
        },
);

const minParts = computed(() => safeParts(props.min));
const maxParts = computed(() => safeParts(props.max));
const selectedParts = computed(() => safeParts(props.modelValue));

const displayValue = computed(() => {
    if (!selectedParts.value) return '';
    return formatCalendarParts(selectedParts.value, system.value, locale.value);
});

const secondaryHint = computed(() => {
    if (!props.modelValue) return '';
    const alt: CalendarSystem =
        system.value === 'gregorian' ? 'jalali' : 'gregorian';
    return formatDate(props.modelValue, alt, locale.value);
});

const viewYear = ref(today.value.year);
const viewMonth = ref(today.value.month);

watch(
    () => [props.modelValue, system.value] as const,
    () => {
        const parts = selectedParts.value ?? today.value;
        viewYear.value = parts.year;
        viewMonth.value = parts.month;
    },
    { immediate: true },
);

const months = computed(() =>
    monthNames(system.value, locale.value).map((label, index) => ({
        value: index + 1,
        label,
    })),
);

const weekdays = computed(() => weekdayLabels(locale.value));
const years = computed(() => yearRange(system.value, viewYear.value));

type Cell = {
    day: number | null;
    muted: boolean;
    year: number;
    month: number;
};

const calendarCells = computed<Cell[]>(() => {
    const y = viewYear.value;
    const m = viewMonth.value;
    const length = monthLength(y, m, system.value);
    const offset = firstWeekdayColumn(y, m, system.value);
    const cells: Cell[] = [];

    for (let i = 0; i < offset; i += 1) {
        cells.push({ day: null, muted: true, year: y, month: m });
    }
    for (let d = 1; d <= length; d += 1) {
        cells.push({ day: d, muted: false, year: y, month: m });
    }
    while (cells.length % 7 !== 0) {
        cells.push({ day: null, muted: true, year: y, month: m });
    }
    return cells;
});

function isSelected(cell: Cell): boolean {
    if (!cell.day || !selectedParts.value) return false;
    return (
        selectedParts.value.year === cell.year &&
        selectedParts.value.month === cell.month &&
        selectedParts.value.day === cell.day
    );
}

function isToday(cell: Cell): boolean {
    if (!cell.day) return false;
    return (
        today.value.year === cell.year &&
        today.value.month === cell.month &&
        today.value.day === cell.day
    );
}

function isDisabled(cell: Cell): boolean {
    if (!cell.day) return false;
    const here = { year: cell.year, month: cell.month, day: cell.day };
    if (maxParts.value && compareParts(here, maxParts.value) > 0) return true;
    if (minParts.value && compareParts(here, minParts.value) < 0) return true;
    return false;
}

function pickDay(cell: Cell): void {
    if (cell.muted || !cell.day || isDisabled(cell)) return;
    const greg = calendarPartsToGregorianString(
        { year: cell.year, month: cell.month, day: cell.day },
        system.value,
    );
    if (!greg) return;
    emit('update:modelValue', greg);
    open.value = false;
}

function prevMonth(): void {
    if (viewMonth.value <= 1) {
        viewMonth.value = 12;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth(): void {
    if (viewMonth.value >= 12) {
        viewMonth.value = 1;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
}

function clear(): void {
    emit('update:modelValue', '');
}

function pickToday(): void {
    const t = today.value;
    if (maxParts.value && compareParts(t, maxParts.value) > 0) return;
    if (minParts.value && compareParts(t, minParts.value) < 0) return;
    emit('update:modelValue', todayGregorianString());
    viewYear.value = t.year;
    viewMonth.value = t.month;
    open.value = false;
}

function toggle(): void {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        const seed = selectedParts.value ?? today.value;
        viewYear.value = seed.year;
        viewMonth.value = seed.month;
        nextTick(() => triggerEl.value?.focus());
    }
}

function onClickAway(event: MouseEvent): void {
    if (!rootEl.value?.contains(event.target as Node)) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('mousedown', onClickAway);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickAway);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            :id="id"
            ref="triggerEl"
            type="button"
            :disabled="disabled"
            :aria-haspopup="'dialog'"
            :aria-expanded="open"
            :aria-required="required || undefined"
            :class="
                cn(
                    'border-input bg-background dark:bg-input/30 flex h-9 w-full min-w-0 items-center gap-2 rounded-md border px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                    props.class,
                )
            "
            @click="toggle"
        >
            <CalendarDays class="text-muted-foreground size-4 shrink-0" />
            <span
                :class="
                    cn(
                        'flex-1 truncate text-start tabular-nums',
                        !displayValue && 'text-muted-foreground',
                    )
                "
            >
                {{
                    displayValue ||
                    placeholder ||
                    t('Select date')
                }}
            </span>
            <button
                v-if="displayValue && !disabled"
                type="button"
                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-full p-0.5 transition"
                :aria-label="t('Clear')"
                @click.stop="clear"
            >
                <X class="size-3.5" />
            </button>
        </button>

        <Transition
            enter-active-class="transition duration-160 ease-out"
            enter-from-class="-translate-y-1 scale-[0.98] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="-translate-y-1 scale-[0.98] opacity-0"
        >
            <div
                v-if="open"
                role="dialog"
                aria-modal="false"
                class="bg-popover text-popover-foreground absolute z-50 mt-1.5 w-[19.5rem] overflow-hidden rounded-xl border shadow-lg"
                @mousedown.stop
            >
                <div
                    class="from-primary/12 via-primary/5 to-transparent border-b bg-gradient-to-br px-3 pt-3 pb-2.5"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p
                            class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                        >
                            {{ systemLabel }}
                        </p>
                        <p
                            v-if="secondaryHint"
                            class="text-muted-foreground text-[10px] tabular-nums"
                        >
                            {{ secondaryHint }}
                        </p>
                    </div>

                    <div class="mt-2 flex items-center gap-1">
                        <button
                            type="button"
                            class="hover:bg-background/80 text-muted-foreground hover:text-foreground rounded-lg p-1.5 transition"
                            :aria-label="t('Previous month')"
                            @click="prevMonth"
                        >
                            <ChevronRight v-if="rtl" class="size-4" />
                            <ChevronLeft v-else class="size-4" />
                        </button>

                        <div class="flex min-w-0 flex-1 items-center gap-1.5">
                            <select
                                v-model.number="viewMonth"
                                class="border-input bg-background/90 hover:bg-background focus:ring-ring/40 h-8 min-w-0 flex-1 rounded-lg border px-2 text-xs font-semibold outline-none focus:ring-2"
                            >
                                <option
                                    v-for="m in months"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </option>
                            </select>
                            <select
                                v-model.number="viewYear"
                                class="border-input bg-background/90 hover:bg-background focus:ring-ring/40 h-8 w-[5.25rem] shrink-0 rounded-lg border px-1.5 text-xs font-semibold tabular-nums outline-none focus:ring-2"
                            >
                                <option
                                    v-for="y in years"
                                    :key="y"
                                    :value="y"
                                >
                                    {{ localizeDigits(y, locale) }}
                                </option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="hover:bg-background/80 text-muted-foreground hover:text-foreground rounded-lg p-1.5 transition"
                            :aria-label="t('Next month')"
                            @click="nextMonth"
                        >
                            <ChevronLeft v-if="rtl" class="size-4" />
                            <ChevronRight v-else class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="px-3 pt-2.5 pb-1">
                    <div
                        class="text-muted-foreground grid grid-cols-7 gap-0.5 text-center text-[10px] font-semibold tracking-wide"
                    >
                        <span
                            v-for="(d, i) in weekdays"
                            :key="i"
                            class="flex h-7 items-center justify-center"
                        >
                            {{ d }}
                        </span>
                    </div>

                    <div class="mt-0.5 grid grid-cols-7 gap-0.5">
                        <button
                            v-for="(cell, idx) in calendarCells"
                            :key="idx"
                            type="button"
                            :disabled="cell.muted || isDisabled(cell)"
                            :class="
                                cn(
                                    'relative flex h-9 items-center justify-center rounded-lg text-sm tabular-nums transition',
                                    cell.muted && 'pointer-events-none text-transparent',
                                    !cell.muted &&
                                        isDisabled(cell) &&
                                        'text-muted-foreground/30 cursor-not-allowed',
                                    !cell.muted &&
                                        !isDisabled(cell) &&
                                        !isSelected(cell) &&
                                        'hover:bg-muted text-foreground',
                                    isSelected(cell) &&
                                        'bg-primary text-primary-foreground shadow-sm',
                                    !isSelected(cell) &&
                                        isToday(cell) &&
                                        'ring-primary/45 ring-1 ring-inset',
                                )
                            "
                            @click="pickDay(cell)"
                        >
                            {{
                                cell.day != null
                                    ? localizeDigits(cell.day, locale)
                                    : ''
                            }}
                        </button>
                    </div>
                </div>

                <div
                    class="bg-muted/30 flex items-center justify-between gap-2 border-t px-3 py-2"
                >
                    <button
                        type="button"
                        class="text-primary hover:bg-primary/10 rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        @click="pickToday"
                    >
                        {{ t('Today') }}
                    </button>
                    <button
                        v-if="displayValue"
                        type="button"
                        class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        @click="clear"
                    >
                        {{ t('Clear') }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
