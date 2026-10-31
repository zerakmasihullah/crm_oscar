<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PlatformSettingsTabs from '@/components/settings/PlatformSettingsTabs.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import SettingsToggleRow from '@/components/settings/SettingsToggleRow.vue';
import { MisSection } from '@/components/mis';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';

type SystemSettings = {
    registration: boolean;
    reset_password: boolean;
    email_verification: boolean;
    multilingual: boolean;
    translations_ui: boolean;
    exports: boolean;
    excel_exports: boolean;
    tours: boolean;
    seo: boolean;
    attachments: boolean;
    audit: boolean;
    api: boolean;
};

type FeatureDefinition = {
    key: keyof SystemSettings;
    group: string;
    label: string;
    help: string;
};

type ModuleLink = {
    key: string;
    label: string;
    href: string;
    enabled: boolean;
    help: string;
};

const props = defineProps<{
    settings: SystemSettings;
    modules: ModuleLink[];
    definitions: FeatureDefinition[];
}>();

const { t } = useTranslations();

const form = useForm({ ...props.settings });

watch(
    () => props.settings,
    (settings) => {
        form.defaults({ ...settings });
        form.reset();
    },
);

const groups = computed(() => {
    const order = ['auth', 'locale', 'product', 'integrations'] as const;
    const labels: Record<(typeof order)[number], string> = {
        auth: t('Authentication'),
        locale: t('Language & locale'),
        product: t('Product features'),
        integrations: t('Integrations'),
    };

    return order
        .map((group) => ({
            id: group,
            title: labels[group],
            items: props.definitions.filter((item) => item.group === group),
        }))
        .filter((group) => group.items.length > 0);
});

function isDisabled(key: keyof SystemSettings): boolean {
    return key === 'excel_exports' && !form.exports;
}

function submit(): void {
    form.put('/settings/system-control', { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Settings', href: '/settings/system-control' },
            { title: 'System Control', href: '/settings/system-control' },
        ],
    },
});
</script>

<template>
    <Head :title="t('System Control')" />

    <SettingsPage>
        <template #tabs>
            <PlatformSettingsTabs />
        </template>

        <div class="space-y-5" data-tour="settings-system-control">
            <SettingsPanel>
                <MisSection
                    :title="t('Features')"
                >
                    <form class="space-y-6" @submit.prevent="submit">
                        <section
                            v-for="group in groups"
                            :key="group.id"
                            class="space-y-2"
                        >
                            <h2
                                class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                            >
                                {{ group.title }}
                            </h2>

                            <div class="divide-y divide-border/60">
                                <SettingsToggleRow
                                    v-for="item in group.items"
                                    :key="item.key"
                                    :title="t(item.label)"
                                    :description="
                                        item.help ? t(item.help) : null
                                    "
                                    :model-value="form[item.key]"
                                    :disabled="isDisabled(item.key)"
                                    @update:model-value="
                                        (value) => (form[item.key] = value)
                                    "
                                />
                            </div>
                        </section>

                        <div class="flex justify-end border-t border-border/50 pt-4">
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.isDirty"
                            >
                                {{ t('Save') }}
                            </Button>
                        </div>
                    </form>
                </MisSection>
            </SettingsPanel>

            <SettingsPanel>
                <MisSection
                    :title="t('Related modules')"
                >
                    <div class="space-y-2">
                        <div
                            v-for="module in modules"
                            :key="module.key"
                            class="flex items-center justify-between gap-4 rounded-xl border border-border/70 px-3.5 py-3"
                        >
                            <div class="min-w-0">
                                <div
                                    class="flex flex-wrap items-center gap-2 text-sm font-medium"
                                >
                                    {{ t(module.label) }}
                                    <Badge
                                        :variant="
                                            module.enabled
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            module.enabled ? t('On') : t('Off')
                                        }}
                                    </Badge>
                                </div>
                            </div>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="module.href">{{
                                    t('Configure')
                                }}</Link>
                            </Button>
                        </div>
                    </div>
                </MisSection>
            </SettingsPanel>
        </div>
    </SettingsPage>
</template>
