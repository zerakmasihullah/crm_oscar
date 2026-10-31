<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FileUploadField, { type ExistingUploadFile } from '@/components/FileUploadField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

type BrandingSettings = {
    app_name: string;
    default_locale: string;
    calendar_system?: 'gregorian' | 'jalali' | 'hijri';
    logo_url: string | null;
    favicon_url: string | null;
};

const props = defineProps<{
    settings: BrandingSettings;
    locales: Array<{ code: string; name: string; native: string }>;
    canManage: boolean;
}>();

const { t } = useTranslations();
const page = usePage();
const logoPreview = ref<string | null>(props.settings.logo_url);
const faviconPreview = ref<string | null>(props.settings.favicon_url);

const form = useForm({
    app_name: props.settings.app_name,
    default_locale: props.settings.default_locale,
    calendar_system: props.settings.calendar_system ?? 'gregorian',
    logo: null as File | null,
    remove_logo: false,
    favicon: null as File | null,
    remove_favicon: false,
});

watch(
    () => props.settings,
    (settings) => {
        form.app_name = settings.app_name;
        form.default_locale = settings.default_locale;
        form.calendar_system = settings.calendar_system ?? 'gregorian';

        if (!form.logo) {
            logoPreview.value = settings.logo_url;
        }

        if (!form.favicon) {
            faviconPreview.value = settings.favicon_url;
        }
    },
    { deep: true },
);

const canEdit = computed(() => props.canManage);

const existingLogo = computed<ExistingUploadFile | null>(() =>
    logoPreview.value && !form.logo
        ? { name: t('Logo'), url: logoPreview.value }
        : null,
);

const existingFavicon = computed<ExistingUploadFile | null>(() =>
    faviconPreview.value && !form.favicon
        ? { name: t('Favicon'), url: faviconPreview.value }
        : null,
);

const calendarOptions = computed(() => [
    {
        value: 'jalali' as const,
        label: t('Jalali (Shamsi)'),
        hint: t('Solar Hijri calendar'),
    },
    {
        value: 'hijri' as const,
        label: t('Hijri (Lunar)'),
        hint: t('Islamic lunar calendar (Umm al-Qura)'),
    },
    {
        value: 'gregorian' as const,
        label: t('Gregorian'),
        hint: t('International civil calendar (CE)'),
    },
]);

function onLogoDeferred(files: File[]): void {
    const file = files[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = file ? URL.createObjectURL(file) : props.settings.logo_url;
}

function onFaviconDeferred(files: File[]): void {
    const file = files[0] ?? null;
    form.favicon = file;
    form.remove_favicon = false;
    faviconPreview.value = file ? URL.createObjectURL(file) : props.settings.favicon_url;
}

function onLogoRemoved(): void {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function onFaviconRemoved(): void {
    form.favicon = null;
    form.remove_favicon = true;
    faviconPreview.value = null;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        _method: 'put',
        remove_logo: data.remove_logo ? 1 : 0,
        remove_favicon: data.remove_favicon ? 1 : 0,
    })).post('/settings/general', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
            form.favicon = null;
            form.remove_logo = false;
            form.remove_favicon = false;

            const favicon =
                (page.props as { favicon?: string | null }).favicon ??
                faviconPreview.value;

            if (favicon) {
                const link =
                    document.querySelector<HTMLLinkElement>(
                        "link[rel='icon']",
                    ) ?? document.createElement('link');
                link.rel = 'icon';
                link.href = favicon;

                if (!link.parentElement) {
                    document.head.appendChild(link);
                }
            }
        },
    });
}
</script>

<template>
    <Card data-tour="branding-settings">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <ImagePlus class="size-4 text-primary" />
                {{ t('Branding') }}
            </CardTitle>
            <CardDescription>
                {{
                    t(
                        'Organization name, logo, favicon, and the default language for new visitors.',
                    )
                }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="brand_app_name">{{ t('App name') }}</Label>
                        <Input
                            id="brand_app_name"
                            v-model="form.app_name"
                            :disabled="!canEdit"
                            required
                        />
                        <InputError :message="form.errors.app_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="brand_default_locale">{{
                            t('Default locale')
                        }}</Label>
                        <div class="relative">
                            <Languages
                                class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <select
                                id="brand_default_locale"
                                v-model="form.default_locale"
                                :disabled="!canEdit"
                                class="w-full rounded-[var(--radius-control)] border bg-background py-2 ps-9 pe-3 text-sm"
                            >
                                <option
                                    v-for="locale in locales"
                                    :key="locale.code"
                                    :value="locale.code"
                                >
                                    {{ locale.native }} ({{ locale.name }})
                                </option>
                            </select>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t(
                                    'Used when a visitor has no language cookie yet.',
                                )
                            }}
                        </p>
                        <InputError :message="form.errors.default_locale" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FileUploadField
                        mode="deferred"
                        accept="image/*"
                        preview="image"
                        :label="t('Logo')"
                        :hint="t('PNG or JPG up to 2MB.')"
                        :max-bytes="2 * 1024 * 1024"
                        :existing="existingLogo"
                        :disabled="!canEdit"
                        :error="form.errors.logo"
                        @update:deferred="onLogoDeferred"
                        @remove="onLogoRemoved"
                    />

                    <FileUploadField
                        mode="deferred"
                        accept="image/*,.ico"
                        preview="image"
                        :label="t('Favicon')"
                        :hint="t('Browser tab icon. PNG, SVG, or ICO up to 1MB.')"
                        :max-bytes="1024 * 1024"
                        :existing="existingFavicon"
                        :disabled="!canEdit"
                        :error="form.errors.favicon"
                        @update:deferred="onFaviconDeferred"
                        @remove="onFaviconRemoved"
                    />
                </div>

                <div class="space-y-3">
                    <div>
                        <Label>{{ t('Default calendar') }}</Label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                t(
                                    'Dates across the app are shown and picked in this calendar. Values are still stored as Gregorian.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in calendarOptions"
                            :key="option.value"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-input px-3 py-3 transition hover:bg-muted/40 has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                        >
                            <input
                                v-model="form.calendar_system"
                                type="radio"
                                class="mt-1 size-4 border-input text-primary accent-primary focus-visible:ring-ring"
                                :value="option.value"
                                name="calendar_system"
                                :disabled="!canEdit"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">
                                    {{ t(option.label) }}
                                </span>
                                <span
                                    class="mt-0.5 block text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ t(option.hint) }}
                                </span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.calendar_system" />
                </div>

                <Button type="submit" :disabled="!canEdit || form.processing">
                    {{ t('Save branding') }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
