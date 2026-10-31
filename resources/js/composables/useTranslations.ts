import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

export type UseTranslationsReturn = {
    translations: ComputedRef<Record<string, string>>;
    t: (key: string, replace?: Record<string, string>) => string;
};

export function useTranslations(): UseTranslationsReturn {
    const page = usePage();

    const translations = computed(
        () => (page.props.translations as Record<string, string> | undefined) ?? {},
    );

    function t(key: string, replace: Record<string, string> = {}): string {
        let value = translations.value[key] ?? key;

        for (const [placeholder, replacement] of Object.entries(replace)) {
            value = value.replaceAll(`:${placeholder}`, replacement);
        }

        return value;
    }

    return {
        translations,
        t,
    };
}
