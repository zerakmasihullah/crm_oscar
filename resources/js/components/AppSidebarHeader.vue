<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import AiHeaderButton from '@/components/AiHeaderButton.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import ChatHeaderButton from '@/components/ChatHeaderButton.vue';
import GlobalSearch from '@/components/GlobalSearch.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import NewOrderHeaderButton from '@/components/NewOrderHeaderButton.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useInitials } from '@/composables/useInitials';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { t } = useTranslations();
const { getInitials } = useInitials();
const { allNavItems, headerTabItems } = useAppNavigation();
const { isCurrentOrParentUrl } = useCurrentUrl();

const user = computed(() => page.props.auth.user);
const name = computed(() => page.props.name as string);

const showAvatar = computed(
    () => Boolean(user.value?.avatar && user.value.avatar !== ''),
);

function isStoreSettingsPath(): boolean {
    return (
        isCurrentOrParentUrl('/settings') ||
        isCurrentOrParentUrl('/menus') ||
        isCurrentOrParentUrl('/menu-items') ||
        isCurrentOrParentUrl('/deliveries') ||
        isCurrentOrParentUrl('/reservations')
    );
}

const activePageIcon = computed(() => {
    if (isStoreSettingsPath()) {
        return (
            allNavItems.value.find((item) => item.title === t('Settings'))
                ?.icon ?? LayoutGrid
        );
    }

    return (
        allNavItems.value.find((item) => {
            if (item.children?.length) {
                return (
                    isCurrentOrParentUrl(item.href) ||
                    item.children.some((child) =>
                        isCurrentOrParentUrl(child.href),
                    )
                );
            }

            return isCurrentOrParentUrl(item.href);
        })?.icon ?? LayoutGrid
    );
});

const crumbs = computed(() => props.breadcrumbs ?? []);

const sectionCrumb = computed(() => crumbs.value[0] ?? null);

const pageTitle = computed(() => {
    const title = sectionCrumb.value?.title ?? '';
    return title ? t(title) : '';
});

const sectionHref = computed(() => {
    const href = sectionCrumb.value?.href;
    if (href) {
        return href;
    }
    return dashboard();
});

const showBreadcrumbs = computed(() => crumbs.value.length > 1);

const isChatsPage = computed(() =>
    String(page.url ?? '').startsWith('/chats'),
);

const isChatThread = computed(
    () => isChatsPage.value && /[?&]user=\d+/.test(String(page.url ?? '')),
);
</script>

<template>
    <header
        class="relative z-20 flex shrink-0 items-center gap-3 md:gap-4"
        :class="
            isChatsPage
                ? isChatThread
                    ? 'hidden md:mt-4 md:flex md:min-h-[4.5rem] md:px-8 md:py-2'
                    : 'mt-2 min-h-12 px-3 py-1.5 ps-14 md:mt-4 md:min-h-[4.5rem] md:px-8 md:py-2'
                : 'mt-3 min-h-[4.5rem] px-5 py-2 md:mt-4 md:px-8'
        "
        data-tour="app-header"
    >
        <div class="relative z-10 flex min-w-0 items-center gap-3">
            <Link
                :href="sectionHref"
                class="hidden size-10 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-white shadow-[0_10px_24px_-12px_rgba(15,23,42,0.45)] sm:inline-flex"
                :aria-label="pageTitle || name"
            >
                <Transition name="header-icon" mode="out-in">
                    <component
                        :is="activePageIcon"
                        :key="toUrl(sectionHref)"
                        class="size-[1.05rem]"
                        stroke-width="1.75"
                    />
                </Transition>
            </Link>

            <div
                v-if="pageTitle || showBreadcrumbs"
                class="relative min-w-0 max-w-[14rem] sm:max-w-[20rem] lg:max-w-[28rem]"
            >
                <Transition name="header-title" mode="out-in">
                    <div :key="pageTitle" class="min-w-0">
                        <Link
                            v-if="pageTitle"
                            :href="sectionHref"
                            class="header-title block truncate pb-0.5 text-base font-semibold tracking-tight text-zinc-900 sm:text-lg dark:text-zinc-50"
                        >
                            {{ pageTitle }}
                            <span class="header-title-line" aria-hidden="true" />
                        </Link>
                        <Breadcrumbs
                            v-if="showBreadcrumbs"
                            :breadcrumbs="crumbs"
                            class="mt-0.5"
                        />
                    </div>
                </Transition>
            </div>
        </div>

        <nav
            v-if="headerTabItems.length"
            class="absolute start-1/2 top-1/2 hidden -translate-x-1/2 -translate-y-1/2 rtl:translate-x-1/2 md:flex"
            aria-label="Section"
        >
            <div class="orvion-glass-pill flex items-center gap-1 p-1.5">
                <Link
                    v-for="item in headerTabItems"
                    :key="String(item.href) + item.title"
                    :href="item.href"
                    class="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium transition"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'bg-zinc-900 text-white shadow-[0_8px_20px_-12px_rgba(15,23,42,0.55)] dark:bg-white dark:text-zinc-900'
                            : 'text-zinc-500 hover:bg-white/50 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white'
                    "
                >
                    <component
                        :is="item.icon"
                        v-if="item.icon"
                        class="size-4 shrink-0"
                        stroke-width="1.75"
                    />
                    <span>{{ item.title }}</span>
                </Link>
            </div>
        </nav>

        <div class="ms-auto flex items-center gap-1.5 sm:gap-2">
            <span class="hidden sm:contents">
                <NewOrderHeaderButton />
            </span>
            <div
                class="orvion-glass-chip inline-flex items-center gap-0.5 rounded-full p-1"
            >
                <GlobalSearch />
                <span class="hidden sm:contents">
                    <LanguageSwitcher />
                </span>
                <ChatHeaderButton />
                <span class="hidden sm:contents">
                    <AiHeaderButton />
                </span>
                <NotificationBell />
            </div>

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        class="size-9 rounded-full p-0 hover:bg-transparent sm:size-10"
                    >
                        <Avatar class="size-9 ring-2 ring-white/80 sm:size-10">
                            <AvatarImage
                                v-if="showAvatar"
                                :src="user.avatar!"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-zinc-900 text-xs font-semibold text-white"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    :side-offset="10"
                    :collision-padding="16"
                    class="min-w-56 rounded-xl border-border/50 bg-white/95 p-1.5 shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-card/95"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>

<style scoped>
.header-title {
    position: relative;
    display: block;
    clip-path: inset(0 0 0 0);
}

.header-title-line {
    position: absolute;
    inset-inline-start: 0;
    bottom: 0;
    height: 1.5px;
    width: 100%;
    border-radius: 999px;
    background: currentColor;
    opacity: 0.22;
    transform: scaleX(0);
    transform-origin: left center;
}

:global([dir='rtl']) .header-title-line {
    transform-origin: right center;
}

/* Wipe reveal — modern app style */
.header-title-enter-active {
    transition:
        opacity 260ms ease,
        transform 480ms cubic-bezier(0.16, 1, 0.3, 1),
        clip-path 480ms cubic-bezier(0.16, 1, 0.3, 1);
}

.header-title-leave-active {
    transition:
        opacity 180ms ease,
        transform 240ms ease,
        clip-path 240ms ease;
}

.header-title-enter-from {
    opacity: 0;
    transform: translateX(-0.65rem);
    clip-path: inset(0 100% 0 0);
}

:global([dir='rtl']) .header-title-enter-from {
    transform: translateX(0.65rem);
    clip-path: inset(0 0 0 100%);
}

.header-title-leave-to {
    opacity: 0;
    transform: translateX(0.4rem);
    clip-path: inset(0 0 0 100%);
}

:global([dir='rtl']) .header-title-leave-to {
    transform: translateX(-0.4rem);
    clip-path: inset(0 100% 0 0);
}

.header-title-enter-active .header-title-line {
    animation: header-line-draw 560ms cubic-bezier(0.16, 1, 0.3, 1) 80ms both;
}

.header-icon-enter-active,
.header-icon-leave-active {
    transition:
        opacity 220ms ease,
        transform 360ms cubic-bezier(0.16, 1, 0.3, 1);
}

.header-icon-enter-from {
    opacity: 0;
    transform: scale(0.8) rotate(-12deg);
}

.header-icon-leave-to {
    opacity: 0;
    transform: scale(0.85) rotate(10deg);
}

@keyframes header-line-draw {
    0% {
        opacity: 0;
        transform: scaleX(0);
    }
    35% {
        opacity: 0.28;
    }
    100% {
        opacity: 0;
        transform: scaleX(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .header-title-enter-active,
    .header-title-leave-active,
    .header-icon-enter-active,
    .header-icon-leave-active {
        transition-duration: 1ms !important;
    }

    .header-title-enter-active .header-title-line {
        animation: none !important;
    }
}
</style>
