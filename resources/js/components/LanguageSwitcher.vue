<script setup lang="ts">
import { Languages } from '@lucide/vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import type { LocaleCode } from '@/types/locale';

const page = usePage();
const { t } = useTranslations();
const { locale, locales, updateLocale } = useLocale();

const multilingual = computed(
    () => page.props.kitFeatures?.multilingual !== false,
);
</script>

<template>
    <DropdownMenu v-if="multilingual && locales.length > 1">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-9 rounded-full text-zinc-700 hover:bg-white/70 hover:text-zinc-900 dark:text-zinc-200 dark:hover:bg-white/10 dark:hover:text-white"
            >
                <Languages class="size-4" stroke-width="1.75" />
                <span class="sr-only">{{ t('Language') }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :side-offset="10"
            :collision-padding="16"
            class="min-w-36 rounded-xl border-border/50 bg-white/95 p-1 shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-card/95"
        >
            <DropdownMenuItem
                v-for="item in locales"
                :key="item.code"
                class="cursor-pointer rounded-lg px-2.5 py-1.5 text-sm"
                :class="item.code === locale ? 'bg-accent font-medium' : ''"
                @click="updateLocale(item.code as LocaleCode)"
            >
                {{ item.native }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
