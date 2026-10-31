<script setup lang="ts">
import { Layers3, Sparkles } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';

const { interfaceStyle, updateInterfaceStyle } = useAppearance();
const { t } = useTranslations();

const styles = [
    { value: 'standard', Icon: Layers3, labelKey: 'Standard' },
    { value: 'glass', Icon: Sparkles, labelKey: 'Glass' },
] as const;
</script>

<template>
    <div
        class="grid w-full gap-3 sm:grid-cols-2"
        role="tablist"
        :aria-label="t('Interface style')"
    >
        <button
            v-for="{ value, Icon, labelKey } in styles"
            :key="value"
            type="button"
            role="tab"
            :aria-selected="interfaceStyle === value"
            class="group flex items-start gap-3 rounded-2xl border p-4 text-start transition"
            :class="
                interfaceStyle === value
                    ? 'border-primary/40 bg-primary/10 text-foreground shadow-soft'
                    : 'border-border/70 bg-card/40 text-muted-foreground hover:border-primary/25 hover:bg-card/70 hover:text-foreground'
            "
            @click="updateInterfaceStyle(value)"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-xl"
                :class="
                    interfaceStyle === value
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground group-hover:text-foreground'
                "
            >
                <component :is="Icon" class="size-4" />
            </span>
            <span class="grid gap-1">
                <strong class="text-sm font-semibold">{{ t(labelKey) }}</strong>
                <small class="text-xs leading-5 text-muted-foreground">
                    {{
                        value === 'glass'
                            ? t(
                                  'Transparent, blurred surfaces that keep your selected colors.',
                              )
                            : t(
                                  'Solid, clean surfaces with your selected color mode.',
                              )
                    }}
                </small>
            </span>
        </button>
    </div>
</template>
