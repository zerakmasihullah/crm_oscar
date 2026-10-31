<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Loader2, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    MisDataTable,
    MisPagination,
    MisSegmentedControl,
} from '@/components/mis';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import type { Paginated } from '@/lib/format';
import type { TextDirection } from '@/types/locale';
import SettingsPage from '@/components/settings/SettingsPage.vue';

interface TranslationEntry {
    key: string;
    value: string;
    missing: boolean;
}

const props = defineProps<{
    entries: Paginated<TranslationEntry>;
    editingLocale: string;
    filters: { search?: string | null; locale: string };
}>();

const { t } = useTranslations();
const { locales } = useLocale();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/profile' },
            { title: 'Translations', href: '/settings/translations' },
        ],
    },
});

const formValues = ref<Record<string, string>>({});

const form = useForm({
    locale: props.editingLocale,
    translations: {} as Record<string, string>,
});

const syncFormValues = (): void => {
    const values: Record<string, string> = {};

    props.entries.data.forEach((entry) => {
        values[entry.key] = entry.value;
    });

    formValues.value = values;
};

watch(() => props.entries, syncFormValues, { immediate: true, deep: true });

const editingDir = computed<TextDirection>(() => {
    return (
        locales.value.find((item) => item.code === props.editingLocale)?.dir ??
        'ltr'
    );
});

const localeTabHref = (code: string): string => {
    const params = new URLSearchParams();

    if (props.filters.search) {
        params.set('search', props.filters.search);
    }

    params.set('locale', code);

    return `/settings/translations?${params.toString()}`;
};

const localeSegments = computed(() =>
    locales.value.map((option) => ({
        id: option.code,
        label: option.native,
        href: localeTabHref(option.code),
    })),
);

const editingLocaleModel = computed({
    get: () => props.editingLocale,
    set: () => undefined,
});

const saveTranslations = (): void => {
    form.locale = props.editingLocale;
    form.translations = { ...formValues.value };
    form.put('/settings/translations', { preserveScroll: true });
};

const search = ref(props.filters.search ?? '');
const selected = ref<Array<string | number>>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        const next = value.trim();
        const current = (props.filters.search ?? '').trim();
        if (next === current) {
            return;
        }

        router.get(
            '/settings/translations',
            {
                locale: props.editingLocale,
                search: next || undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 300);
});

const columns = computed(() => [
    { key: 'key', label: t('Key'), mono: true, truncate: true },
    { key: 'value', label: t('Translation') },
]);

const tableRows = computed(() =>
    props.entries.data.map((entry) => ({
        ...entry,
        key: entry.key,
        value: entry.value,
    })),
);

const addOpen = ref(false);

const addForm = useForm({
    code: '',
    name: '',
    native: '',
    dir: 'ltr' as TextDirection,
    from: 'en',
});

const openAddLanguage = (): void => {
    addForm.reset();
    addForm.clearErrors();
    addForm.code = '';
    addForm.name = '';
    addForm.native = '';
    addForm.dir = 'ltr';
    addForm.from = 'en';
    addOpen.value = true;
};

const submitAddLanguage = (): void => {
    addForm.code = addForm.code.trim().toLowerCase();
    addForm
        .transform((data) => ({
            ...data,
            code: data.code.trim().toLowerCase(),
        }))
        .post('/settings/translations', {
            preserveScroll: true,
            onSuccess: () => {
                addOpen.value = false;
                addForm.reset();
            },
        });
};

const setAddDir = (value: string | number | bigint | Record<string, never> | null): void => {
    addForm.dir = value === 'rtl' ? 'rtl' : 'ltr';
};

const setAddFrom = (value: string | number | bigint | Record<string, never> | null): void => {
    addForm.from = String(value ?? 'en');
};
</script>

<template>
    <Head :title="t('Translations')" />

    <SettingsPage>
        <div class="mb-4">
            <MisSegmentedControl
                v-model="editingLocaleModel"
                :items="localeSegments"
            />
        </div>

        <MisDataTable
            v-model:selected="selected"
            v-model:search="search"
            :columns="columns"
            :rows="tableRows"
            row-key="key"
            toolbar
            searchable
            selectable
            selection-toggle
            column-toggle
            export-filename="translations"
            :search-placeholder="t('Search')"
            :empty-label="t('No translations found')"
        >
            <template #actions>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    class="size-8 rounded-lg"
                    :title="t('Add language')"
                    :aria-label="t('Add language')"
                    @click="openAddLanguage"
                >
                    <Plus class="size-4" />
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="h-8 rounded-lg"
                    :disabled="form.processing"
                    @click="saveTranslations"
                >
                    <Loader2
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    {{ t('Save') }}
                </Button>
            </template>

            <template #cell-key="{ row }">
                <div class="min-w-0">
                    <p class="font-mono text-xs break-all text-muted-foreground">
                        {{ row.key }}
                    </p>
                    <p
                        v-if="row.missing"
                        class="text-xs text-amber-600 dark:text-amber-400"
                    >
                        {{ t('Missing translation') }}
                    </p>
                </div>
            </template>

            <template #cell-value="{ row }">
                <Input
                    v-model="formValues[String(row.key)]"
                    class="text-sm"
                    :dir="editingDir"
                    data-no-row-click
                />
            </template>

            <template #footer>
                <MisPagination :pagination="entries" />
            </template>
        </MisDataTable>

    <Dialog :open="addOpen" @update:open="(v) => (addOpen = v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('Add language') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'Create a new language by copying translations from an existing one. Choose RTL for right-to-left scripts.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitAddLanguage">
                <div class="space-y-2">
                    <Label for="locale-code">{{ t('Language code') }}</Label>
                    <Input
                        id="locale-code"
                        v-model="addForm.code"
                        placeholder="ps"
                        class="font-mono lowercase"
                        autocomplete="off"
                    />
                    <p
                        v-if="addForm.errors.code"
                        class="text-xs text-destructive"
                    >
                        {{ addForm.errors.code }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="locale-name">{{ t('English name') }}</Label>
                        <Input
                            id="locale-name"
                            v-model="addForm.name"
                            placeholder="French"
                        />
                        <p
                            v-if="addForm.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ addForm.errors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="locale-native">{{ t('Native name') }}</Label>
                        <Input
                            id="locale-native"
                            v-model="addForm.native"
                            placeholder="اردو"
                            :dir="addForm.dir"
                        />
                        <p
                            v-if="addForm.errors.native"
                            class="text-xs text-destructive"
                        >
                            {{ addForm.errors.native }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('Text direction') }}</Label>
                        <Select
                            :model-value="addForm.dir"
                            @update:model-value="setAddDir"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue
                                    :placeholder="t('Text direction')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ltr">
                                    {{ t('Left to right') }} (LTR)
                                </SelectItem>
                                <SelectItem value="rtl">
                                    {{ t('Right to left') }} (RTL)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="addForm.errors.dir"
                            class="text-xs text-destructive"
                        >
                            {{ addForm.errors.dir }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t('Copy from') }}</Label>
                        <Select
                            :model-value="addForm.from"
                            @update:model-value="setAddFrom"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue :placeholder="t('Copy from')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in locales"
                                    :key="option.code"
                                    :value="option.code"
                                >
                                    {{ option.native }} ({{ option.code }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="addForm.errors.from"
                            class="text-xs text-destructive"
                        >
                            {{ addForm.errors.from }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="addOpen = false"
                    >
                        {{ t('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="addForm.processing">
                        <Loader2
                            v-if="addForm.processing"
                            class="size-4 animate-spin"
                        />
                        {{ t('Add language') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
    </SettingsPage>
</template>
