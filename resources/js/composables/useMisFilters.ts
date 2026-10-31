import { router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

type FilterValue = string | number | boolean | null | undefined;
type FilterRecord = Record<string, FilterValue>;

type UseMisFiltersOptions = {
    /** Inertia `only` keys for partial reloads. */
    only?: string[];
    preserveScroll?: boolean;
    /** Debounce ms for live search keys (0 = only on apply). */
    debounceMs?: number;
    /** Keys that trigger live debounced apply when changed. */
    liveKeys?: string[];
};

function omitEmpty(filters: FilterRecord): Record<string, string | number | boolean> {
    const query: Record<string, string | number | boolean> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        query[key] = value;
    }

    return query;
}

/**
 * Local filter state + Inertia GET apply/clear helpers for MIS list pages.
 * Supports optional debounced live search for high-frequency typing.
 */
export function useMisFilters<T extends FilterRecord>(
    url: string,
    initial: T,
    defaults: Partial<T> = {},
    options: UseMisFiltersOptions = {},
) {
    const filters = reactive({ ...defaults, ...initial }) as T;
    const pending = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function apply(extra: Partial<T> = {}): void {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        Object.assign(filters, extra);
        pending.value = true;
        router.get(url, omitEmpty(filters as FilterRecord), {
            preserveState: true,
            replace: true,
            preserveScroll: options.preserveScroll ?? true,
            only: options.only,
            onFinish: () => {
                pending.value = false;
            },
        });
    }

    function clear(): void {
        for (const key of Object.keys(filters)) {
            const fallback = defaults[key as keyof T];
            (filters as FilterRecord)[key] =
                fallback === undefined ? '' : (fallback as FilterValue);
        }
        apply();
    }

    function set(patch: Partial<T>): void {
        Object.assign(filters, patch);
    }

    const liveKeys = options.liveKeys ?? [];
    const debounceMs = options.debounceMs ?? 300;

    if (liveKeys.length > 0 && debounceMs > 0) {
        watch(
            () => liveKeys.map((key) => (filters as FilterRecord)[key]),
            () => {
                if (timer) {
                    clearTimeout(timer);
                }
                timer = setTimeout(() => apply(), debounceMs);
            },
        );
    }

    return {
        filters,
        pending,
        apply,
        clear,
        set,
    };
}
