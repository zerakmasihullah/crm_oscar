<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import SettingsContentSkeleton from '@/components/settings/SettingsContentSkeleton.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSettingsNavigation } from '@/composables/useSettingsNavigation';
import { useTranslations } from '@/composables/useTranslations';
import { cn, toUrl } from '@/lib/utils';

const { t } = useTranslations();
const { currentUrl } = useCurrentUrl();
const { navItems, accountItems, adminItems, isItemActive } =
    useSettingsNavigation();

const navigating = ref(false);
const skeletonVariant = ref<'form' | 'list'>('list');

let removeStart: (() => void) | undefined;
let removeFinish: (() => void) | undefined;
let removeError: (() => void) | undefined;
let removeCancel: (() => void) | undefined;

function isSettingsManagementPath(pathname: string): boolean {
    if (
        pathname.startsWith('/menus') ||
        pathname.startsWith('/menu-items') ||
        pathname.startsWith('/deliveries') ||
        pathname.startsWith('/reservations')
    ) {
        return true;
    }

    if (!pathname.startsWith('/settings')) {
        return false;
    }

    const account = [
        '/settings/profile',
        '/settings/security',
        '/settings/appearance',
        '/settings/password',
    ];

    return !account.some(
        (path) => pathname === path || pathname.startsWith(`${path}/`),
    );
}

function skeletonVariantForPath(pathname: string): 'form' | 'list' {
    if (
        pathname.includes('/create') ||
        pathname.includes('/edit') ||
        pathname.endsWith('/offline') ||
        pathname.endsWith('/google-auth') ||
        pathname.endsWith('/system-control') ||
        pathname.endsWith('/general') ||
        pathname.includes('/ai') ||
        pathname.includes('/email') ||
        pathname.includes('/translations') ||
        pathname.includes('/platforms')
    ) {
        return 'form';
    }

    return 'list';
}

onMounted(() => {
    removeStart = router.on('start', (event) => {
        const visit = event.detail.visit;

        if (visit.method.toLowerCase() !== 'get') {
            return;
        }

        const pathname = visit.url.pathname;

        if (!isSettingsManagementPath(pathname)) {
            return;
        }

        skeletonVariant.value = skeletonVariantForPath(pathname);
        navigating.value = true;
    });

    removeFinish = router.on('finish', () => {
        navigating.value = false;
    });

    removeError = router.on('error', () => {
        navigating.value = false;
    });

    removeCancel = router.on('cancel', () => {
        navigating.value = false;
    });
});

onUnmounted(() => {
    removeStart?.();
    removeFinish?.();
    removeError?.();
    removeCancel?.();
});

const activeItem = computed(
    () =>
        navItems.value.find((item) =>
            isItemActive(item, currentUrl.value),
        ) ?? null,
);

function navClass(active: boolean): string {
    return cn(
        'group flex items-center gap-2.5 rounded-xl px-2.5 py-2 text-[13px] font-medium transition',
        active
            ? 'bg-zinc-900 text-white shadow-[0_10px_20px_-14px_rgba(15,23,42,0.55)] dark:bg-white dark:text-zinc-900'
            : 'text-zinc-600 hover:bg-zinc-900/5 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5 dark:hover:text-white',
    );
}

function iconClass(active: boolean): string {
    return cn(
        'inline-flex size-7 shrink-0 items-center justify-center rounded-lg transition',
        active
            ? 'bg-white/15 text-white dark:bg-zinc-900/10 dark:text-zinc-900'
            : 'bg-zinc-900/5 text-zinc-500 group-hover:text-zinc-800 dark:bg-white/10 dark:text-zinc-300',
    );
}
</script>

<template>
    <div class="px-1 py-2 md:px-0" data-tour="settings-layout">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:gap-7">
            <aside
                class="w-full shrink-0 lg:sticky lg:top-[88px] lg:w-[240px]"
                data-tour="settings-nav"
                :aria-label="t('Settings')"
            >
                <div class="mb-2 lg:hidden">
                    <div
                        class="no-scrollbar flex gap-1 overflow-x-auto rounded-2xl border border-border/70 bg-card/90 p-1.5 shadow-soft dark:border-white/10"
                    >
                        <Link
                            v-for="item in navItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            :title="item.title"
                            :class="[
                                'flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition',
                                isItemActive(item, currentUrl)
                                    ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                    : 'text-zinc-600 hover:bg-zinc-900/5 dark:text-zinc-300 dark:hover:bg-white/5',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                class="size-3.5 shrink-0"
                                :stroke-width="1.75"
                            />
                            <span class="max-w-[8rem] truncate">{{
                                item.title
                            }}</span>
                        </Link>
                    </div>
                </div>

                <div
                    class="hidden rounded-2xl border border-border/70 bg-card/90 p-2 shadow-soft dark:border-white/10 lg:block"
                >
                    <div class="px-2.5 pt-2 pb-2">
                        <p class="text-sm font-semibold tracking-tight">
                            {{ t('Settings') }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            {{ activeItem?.title ?? t('Pick a category') }}
                        </p>
                    </div>

                    <nav class="space-y-3 p-1">
                        <div class="space-y-0.5">
                            <p
                                class="px-2.5 pb-1 text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                            >
                                {{ t('Account') }}
                            </p>
                            <Link
                                v-for="item in accountItems"
                                :key="toUrl(item.href)"
                                :href="item.href"
                                :class="
                                    navClass(isItemActive(item, currentUrl))
                                "
                            >
                                <span
                                    :class="
                                        iconClass(
                                            isItemActive(item, currentUrl),
                                        )
                                    "
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-3.5"
                                        :stroke-width="1.75"
                                    />
                                </span>
                                <span class="truncate">{{ item.title }}</span>
                            </Link>
                        </div>

                        <div v-if="adminItems.length" class="space-y-0.5">
                            <div
                                class="mx-2 my-1 h-px bg-border/80"
                                aria-hidden="true"
                            />
                            <p
                                class="px-2.5 pb-1 text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"
                            >
                                {{ t('Administration') }}
                            </p>
                            <Link
                                v-for="item in adminItems"
                                :key="toUrl(item.href)"
                                :href="item.href"
                                :class="
                                    navClass(isItemActive(item, currentUrl))
                                "
                            >
                                <span
                                    :class="
                                        iconClass(
                                            isItemActive(item, currentUrl),
                                        )
                                    "
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-3.5"
                                        :stroke-width="1.75"
                                    />
                                </span>
                                <span class="truncate">{{ item.title }}</span>
                            </Link>
                        </div>
                    </nav>
                </div>
            </aside>

            <section class="min-w-0 flex-1">
                <div class="w-full space-y-6">
                    <SettingsContentSkeleton
                        v-if="navigating"
                        :variant="skeletonVariant"
                    />
                    <div v-show="!navigating" class="w-full space-y-6">
                        <slot />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
