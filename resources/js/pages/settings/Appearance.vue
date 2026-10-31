<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Brush, Image, Layers, SwatchBook } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import BrandingSettings from '@/components/BrandingSettings.vue';
import InterfaceStyleTabs from '@/components/InterfaceStyleTabs.vue';
import { MisSection, MisTabs } from '@/components/mis';
import type { MisTabItem } from '@/components/mis';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import ThemeColorSettings from '@/components/ThemeColorSettings.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ThemeColorSettings as ThemeColors } from '@/lib/theme-colors';
import { edit } from '@/routes/appearance';

const props = defineProps<{
    themeColors: ThemeColors;
    themeColorsCustomized: boolean;
    canManageTheme: boolean;
    branding: {
        app_name: string;
        default_locale: string;
        calendar_system?: 'gregorian' | 'jalali' | 'hijri';
        logo_url: string | null;
        favicon_url: string | null;
    };
    locales: Array<{ code: string; name: string; native: string }>;
}>();

type AppearanceTab = 'colors' | 'branding';

const { t } = useTranslations();
const activeTab = ref<AppearanceTab>('colors');

const tabs = computed<MisTabItem[]>(() => {
    if (!props.canManageTheme) {
        return [];
    }

    return [
        { id: 'colors', label: t('Colors'), icon: SwatchBook },
        { id: 'branding', label: t('Branding'), icon: Image },
    ];
});

const showTabs = computed(() => tabs.value.length > 1);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Appearance settings')" />

    <h1 class="sr-only">{{ t('Appearance settings') }}</h1>

    <SettingsPage>
        <template #tabs>
            <MisTabs v-if="showTabs" v-model="activeTab" :tabs="tabs" />
        </template>

        <SettingsPanel v-show="!showTabs || activeTab === 'colors'">
            <MisSection
                :title="t('Color mode')"
                :icon="Brush"
            >
                <AppearanceTabs />
            </MisSection>

            <div class="border-t border-border/50 pt-5">
                <MisSection
                    :title="t('Interface style')"
                    :icon="Layers"
                >
                    <InterfaceStyleTabs />
                </MisSection>
            </div>

            <div
                v-if="canManageTheme"
                class="border-t border-border/50 pt-5"
            >
                <ThemeColorSettings />
            </div>
        </SettingsPanel>

        <SettingsPanel
            v-if="canManageTheme"
            v-show="activeTab === 'branding'"
        >
            <BrandingSettings
                :settings="branding"
                :locales="locales"
                :can-manage="canManageTheme"
            />
        </SettingsPanel>
    </SettingsPage>
</template>
