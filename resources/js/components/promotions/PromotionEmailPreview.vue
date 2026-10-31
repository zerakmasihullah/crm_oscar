<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { ImageIcon } from '@lucide/vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps<{
    subject: string;
    content: string;
    template: string;
    imageFile?: File | null;
    existingImageUrl?: string | null;
    removeImage?: boolean;
}>();

const { t } = useTranslations();
const blobUrl = ref<string | null>(null);

watch(
    () => props.imageFile,
    (file) => {
        if (blobUrl.value) {
            URL.revokeObjectURL(blobUrl.value);
            blobUrl.value = null;
        }
        if (file) blobUrl.value = URL.createObjectURL(file);
    },
    { immediate: true },
);

onUnmounted(() => {
    if (blobUrl.value) URL.revokeObjectURL(blobUrl.value);
});

const imageUrl = computed(() => {
    if (blobUrl.value) return blobUrl.value;
    if (props.removeImage) return null;
    return props.existingImageUrl || null;
});

const displaySubject = computed(
    () => props.subject.trim() || t('Your subject line'),
);

const plainBody = computed(() =>
    props.content.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim(),
);

const hasBody = computed(() => plainBody.value.length > 0);

type Theme = {
    bg: string;
    headerBg: string;
    headerText: string;
    muted: string;
    body: string;
    cta: string;
    ctaRadius: string;
    badge: boolean;
    imageFirst: boolean;
    split: boolean;
    cardPad: boolean;
};

const theme = computed<Theme>(() => {
    const themes: Record<string, Theme> = {
        'classic-banner': {
            bg: '#ffffff',
            headerBg: 'linear-gradient(135deg,#EA7516,#f97316)',
            headerText: '#ffffff',
            muted: '#ffffffb3',
            body: '#3f3f46',
            cta: 'linear-gradient(135deg,#EA7516,#f97316)',
            ctaRadius: '10px',
            badge: true,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        'discount-badge': {
            bg: '#ffffff',
            headerBg: 'linear-gradient(135deg,#dc2626,#EA7516)',
            headerText: '#ffffff',
            muted: '#ffffffb3',
            body: '#3f3f46',
            cta: 'linear-gradient(135deg,#dc2626,#EA7516)',
            ctaRadius: '999px',
            badge: true,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        'product-showcase': {
            bg: '#ffffff',
            headerBg: '#111827',
            headerText: '#ffffff',
            muted: '#ffffff99',
            body: '#3f3f46',
            cta: '#EA7516',
            ctaRadius: '8px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        'full-width-image': {
            bg: '#ffffff',
            headerBg: '#ffffff',
            headerText: '#18181b',
            muted: '#a1a1aa',
            body: '#52525b',
            cta: '#EA7516',
            ctaRadius: '8px',
            badge: false,
            imageFirst: true,
            split: false,
            cardPad: false,
        },
        'newsletter-style': {
            bg: '#ffffff',
            headerBg: '#f8fafc',
            headerText: '#0f172a',
            muted: '#94a3b8',
            body: '#475569',
            cta: '#EA7516',
            ctaRadius: '6px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        minimalist: {
            bg: '#ffffff',
            headerBg: '#ffffff',
            headerText: '#18181b',
            muted: '#a1a1aa',
            body: '#52525b',
            cta: '#18181b',
            ctaRadius: '6px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        'modern-card': {
            bg: '#ffffff',
            headerBg: '#0f172a',
            headerText: '#ffffff',
            muted: '#ffffff99',
            body: '#334155',
            cta: 'linear-gradient(135deg,#EA7516,#f97316)',
            ctaRadius: '12px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: true,
        },
        'split-layout': {
            bg: '#ffffff',
            headerBg: '#EA7516',
            headerText: '#ffffff',
            muted: '#ffffffb3',
            body: '#3f3f46',
            cta: '#EA7516',
            ctaRadius: '8px',
            badge: false,
            imageFirst: false,
            split: true,
            cardPad: false,
        },
        'product-grid': {
            bg: '#ffffff',
            headerBg: '#111827',
            headerText: '#ffffff',
            muted: '#ffffff99',
            body: '#3f3f46',
            cta: '#EA7516',
            ctaRadius: '8px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
        elegant: {
            bg: '#fafaf9',
            headerBg: '#1c1917',
            headerText: '#fafaf9',
            muted: '#a8a29e',
            body: '#57534e',
            cta: '#1c1917',
            ctaRadius: '2px',
            badge: false,
            imageFirst: false,
            split: false,
            cardPad: false,
        },
    };
    return themes[props.template] ?? themes['classic-banner'];
});
</script>

<template>
    <div class="flex flex-col overflow-hidden rounded-3xl border border-border/70 bg-zinc-100 dark:bg-zinc-900/40">
        <!-- Fake mail chrome -->
        <div class="flex items-center gap-2 border-b border-border/60 bg-card/80 px-4 py-3 backdrop-blur">
            <span class="size-2.5 rounded-full bg-red-400/80" />
            <span class="size-2.5 rounded-full bg-amber-400/80" />
            <span class="size-2.5 rounded-full bg-emerald-400/80" />
            <span class="ml-2 truncate text-xs text-muted-foreground">
                {{ displaySubject }}
            </span>
        </div>

        <div class="flex-1 p-4 sm:p-5">
            <div
                class="mx-auto overflow-hidden shadow-xl shadow-black/10 transition-all duration-300"
                :class="theme.cardPad ? 'rounded-2xl' : 'rounded-xl'"
                :style="{ background: theme.bg, maxWidth: '340px' }"
            >
                <!-- Split -->
                <template v-if="theme.split">
                    <div class="grid grid-cols-2">
                        <div
                            class="flex flex-col justify-center gap-2.5 p-4"
                            :style="{ background: theme.headerBg, color: theme.headerText }"
                        >
                            <p class="text-[11px] font-medium uppercase tracking-wider opacity-70">
                                Oscar’s
                            </p>
                            <p class="text-sm font-semibold leading-snug">{{ displaySubject }}</p>
                            <span
                                class="mt-1 inline-flex w-fit rounded-md bg-white/20 px-2.5 py-1 text-[10px] font-semibold"
                            >
                                {{ t('Order now') }}
                            </span>
                        </div>
                        <div
                            v-if="imageUrl"
                            class="min-h-[150px] bg-cover bg-center"
                            :style="{ backgroundImage: `url(${imageUrl})` }"
                        />
                        <div
                            v-else
                            class="flex min-h-[150px] flex-col items-center justify-center gap-1 bg-zinc-100 text-zinc-400"
                        >
                            <ImageIcon class="size-5" />
                            <span class="text-[10px]">{{ t('Add photo') }}</span>
                        </div>
                    </div>
                    <div class="space-y-3 px-4 py-4">
                        <div
                            v-if="hasBody"
                            class="text-[13px] leading-relaxed"
                            :style="{ color: theme.body }"
                            v-html="content"
                        />
                        <p v-else class="text-[13px] text-zinc-400">{{ t('Write your message…') }}</p>
                    </div>
                </template>

                <!-- Image first -->
                <template v-else-if="theme.imageFirst">
                    <div
                        v-if="imageUrl"
                        class="aspect-[4/3] bg-cover bg-center"
                        :style="{ backgroundImage: `url(${imageUrl})` }"
                    />
                    <div
                        v-else
                        class="flex aspect-[4/3] flex-col items-center justify-center gap-2 bg-zinc-100 text-zinc-400"
                    >
                        <ImageIcon class="size-7" />
                        <span class="text-xs">{{ t('Hero photo shows here') }}</span>
                    </div>
                    <div class="space-y-3 px-5 py-5">
                        <h3 class="text-[17px] font-semibold tracking-tight" :style="{ color: theme.headerText }">
                            {{ displaySubject }}
                        </h3>
                        <div
                            v-if="hasBody"
                            class="text-[13px] leading-relaxed"
                            :style="{ color: theme.body }"
                            v-html="content"
                        />
                        <p v-else class="text-[13px] text-zinc-400">{{ t('Write your message…') }}</p>
                        <div class="pt-1">
                            <span
                                class="inline-block px-4 py-2.5 text-[12px] font-semibold text-white"
                                :style="{ background: theme.cta, borderRadius: theme.ctaRadius }"
                            >
                                {{ t('Order now') }} →
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Default -->
                <template v-else>
                    <div
                        class="px-5 py-7 text-center"
                        :style="{ background: theme.headerBg, color: theme.headerText }"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-[0.2em] uppercase"
                            :style="{ color: theme.muted }"
                        >
                            Oscar’s
                        </p>
                        <h3 class="mt-2 text-[18px] font-semibold leading-snug tracking-tight">
                            {{ displaySubject }}
                        </h3>
                        <span
                            v-if="theme.badge"
                            class="mt-3 inline-flex rounded-full border border-white/25 bg-white/15 px-3 py-1 text-[10px] font-semibold"
                        >
                            {{ t('Special offer') }}
                        </span>
                    </div>

                    <div
                        v-if="imageUrl"
                        class="aspect-[16/10] bg-cover bg-center"
                        :class="theme.cardPad ? 'mx-4 mt-4 rounded-xl' : ''"
                        :style="{ backgroundImage: `url(${imageUrl})` }"
                    />
                    <div
                        v-else
                        class="flex aspect-[16/10] flex-col items-center justify-center gap-2 border-b border-dashed border-zinc-200 bg-zinc-50 text-zinc-400"
                        :class="theme.cardPad ? 'mx-4 mt-4 rounded-xl border' : ''"
                    >
                        <ImageIcon class="size-6" />
                        <span class="text-[11px]">{{ t('Drop a photo to feature it') }}</span>
                    </div>

                    <div class="space-y-3.5 px-5 py-5">
                        <div
                            v-if="hasBody"
                            class="text-[13px] leading-relaxed"
                            :style="{ color: theme.body }"
                            v-html="content"
                        />
                        <p v-else class="text-[13px] text-zinc-400">{{ t('Write your message…') }}</p>
                        <div class="pt-1 text-center">
                            <span
                                class="inline-block px-5 py-2.5 text-[13px] font-semibold text-white shadow-sm"
                                :style="{ background: theme.cta, borderRadius: theme.ctaRadius }"
                            >
                                {{ t('Order now') }} →
                            </span>
                        </div>
                    </div>
                </template>

                <div class="border-t border-zinc-100 px-4 py-3 text-center">
                    <p class="text-[10px] leading-relaxed text-zinc-400">
                        Oscar’s · Limerick
                        <br />
                        {{ t('Unsubscribe anytime') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
