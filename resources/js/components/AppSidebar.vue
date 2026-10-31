<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Menu, Settings } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useSidebar } from '@/components/ui/sidebar';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useLocale } from '@/composables/useLocale';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const page = usePage();
const { t } = useTranslations();
const { dir, sidebarSide } = useLocale();
const { isMobile, open, openMobile, setOpen, setOpenMobile } = useSidebar();
const { allNavItems } = useAppNavigation();
const { isCurrentOrParentUrl } = useCurrentUrl();

const name = computed(() => page.props.name as string);
const sheetSide = computed(() => (dir.value === 'rtl' ? 'right' : 'left'));
const expanded = computed(() => open.value && !isMobile.value);
const tooltipSide = computed(() =>
    sidebarSide.value === 'right' ? 'left' : 'right',
);

const settingsItem = computed<NavItem | null>(() => {
    return (
        allNavItems.value.find((item) => item.title === t('Settings')) ?? null
    );
});

const settingsActive = computed(() => isCurrentOrParentUrl('/settings'));

/** Hide floating menu while a chat thread is open so it doesn’t cover Back. */
const hideMobileMenuFab = computed(() => {
    const url = String(page.url ?? '');
    return url.startsWith('/chats') && /[?&]user=\d+/.test(url);
});

const railItems = computed(() =>
    allNavItems.value.filter((item) => item.title !== t('Settings')),
);

const ToggleIcon = computed(() => {
    const pointingIn =
        sidebarSide.value === 'right' ? expanded.value : !expanded.value;

    return pointingIn ? ArrowRight : ArrowLeft;
});

function onNavigate() {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}

function onToggle() {
    if (isMobile.value) {
        setOpenMobile(!openMobile.value);
        return;
    }
    setOpen(!open.value);
}

function isItemActive(item: NavItem): boolean {
    if (item.title === t('Settings')) {
        return settingsActive.value;
    }

    if (item.children?.length) {
        return (
            isCurrentOrParentUrl(item.href) ||
            item.children.some((child) => isCurrentOrParentUrl(child.href))
        );
    }

    return isCurrentOrParentUrl(item.href);
}

function itemClasses(active: boolean) {
    if (expanded.value) {
        return active
            ? 'h-11 w-full justify-start gap-3 rounded-full bg-zinc-900 px-1.5 pe-3 text-white shadow-[0_14px_28px_-16px_rgba(15,23,42,0.65)] hover:bg-zinc-900 hover:text-white dark:bg-white dark:text-zinc-900 dark:hover:bg-white dark:hover:text-zinc-900'
            : 'h-11 w-full justify-start gap-3 rounded-full px-1.5 pe-3 text-zinc-600 hover:bg-white/60 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white';
    }

    return active
        ? 'size-11 justify-center rounded-full text-white'
        : 'size-11 justify-center rounded-full text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white';
}

function chipClasses(active: boolean) {
    if (expanded.value) {
        return active
            ? 'bg-transparent text-white dark:text-zinc-900'
            : 'orvion-side-chip text-zinc-700 dark:text-zinc-200';
    }

    return active
        ? 'bg-zinc-900 text-white shadow-[0_12px_28px_-14px_rgba(15,23,42,0.7)] dark:bg-white dark:text-zinc-900'
        : 'orvion-side-chip text-zinc-700 dark:text-zinc-200';
}
</script>

<template>
    <Button
        v-if="!hideMobileMenuFab"
        type="button"
        variant="ghost"
        size="icon"
        class="fixed start-3 top-4 z-30 size-10 rounded-full bg-white text-zinc-900 shadow-[0_8px_24px_-12px_rgba(15,23,42,0.35)] md:hidden dark:bg-card dark:text-foreground dark:ring-1 dark:ring-white/10"
        :aria-label="t('Expand sidebar')"
        @click="setOpenMobile(true)"
    >
        <Menu class="size-4" />
    </Button>

    <aside
        :class="
            cn(
                'app-sidebar relative z-20 my-3 ms-3 hidden h-[calc(100svh-1.5rem)] shrink-0 md:flex',
                expanded ? 'w-[15.5rem] is-expanded' : 'w-[4.25rem] is-collapsed',
                props.class,
            )
        "
        data-tour="app-sidebar"
        data-sidebar="sidebar"
        :data-state="expanded ? 'expanded' : 'collapsed'"
    >
        <div class="flex h-full w-full flex-col bg-transparent p-1.5">
            <div class="flex flex-1 flex-col justify-center">
                <nav
                    :class="
                        cn(
                            'app-sidebar-nav flex flex-col',
                            expanded
                                ? 'orvion-side-panel gap-1.5 p-2.5'
                                : 'items-center gap-3',
                        )
                    "
                    data-tour="sidebar-nav"
                    :aria-label="name"
                >
                    <Tooltip
                        v-for="(item, index) in railItems"
                        :key="String(item.href)"
                        :delay-duration="120"
                    >
                        <TooltipTrigger as-child>
                            <Link
                                :href="item.href"
                                :title="expanded ? undefined : item.title"
                                class="app-sidebar-item group inline-flex items-center text-sm font-medium"
                                :class="itemClasses(isItemActive(item))"
                                :style="{ '--item-i': index }"
                                @click="onNavigate"
                            >
                                <span
                                    class="app-sidebar-chip inline-flex size-11 shrink-0 items-center justify-center rounded-full"
                                    :class="chipClasses(isItemActive(item))"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-[1.05rem] transition-transform duration-300 ease-out group-hover:scale-110"
                                        stroke-width="1.75"
                                    />
                                </span>
                                <span
                                    class="app-sidebar-label truncate font-medium pe-1"
                                    :class="expanded ? 'is-shown' : 'is-hidden'"
                                >
                                    {{ item.title }}
                                </span>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent
                            v-if="!expanded"
                            :side="tooltipSide"
                            :side-offset="2"
                            :avoid-collisions="false"
                            class="rounded-full border-0 bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white shadow-lg [&_[class*='fill-foreground']]:hidden"
                        >
                            {{ item.title }}
                        </TooltipContent>
                    </Tooltip>
                </nav>
            </div>

            <div
                class="mt-auto flex flex-col gap-3 pt-3"
                :class="expanded ? 'gap-1.5' : 'items-center'"
            >
                <Tooltip v-if="settingsItem" :delay-duration="120">
                    <TooltipTrigger as-child>
                        <Link
                            :href="settingsItem.href"
                            :title="expanded ? undefined : settingsItem.title"
                            class="app-sidebar-item group inline-flex items-center text-sm font-medium"
                            :class="itemClasses(settingsActive)"
                            :style="{ '--item-i': railItems.length }"
                            @click="onNavigate"
                        >
                            <span
                                class="app-sidebar-chip inline-flex size-11 shrink-0 items-center justify-center rounded-full"
                                :class="chipClasses(settingsActive)"
                            >
                                <Settings
                                    class="size-[1.05rem] transition-transform duration-300 ease-out group-hover:scale-110"
                                    stroke-width="1.75"
                                />
                            </span>
                            <span
                                class="app-sidebar-label truncate font-medium pe-1"
                                :class="expanded ? 'is-shown' : 'is-hidden'"
                            >
                                {{ settingsItem.title }}
                            </span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="!expanded"
                        :side="tooltipSide"
                        :side-offset="2"
                        :avoid-collisions="false"
                        class="rounded-full border-0 bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white shadow-lg [&_[class*='fill-foreground']]:hidden"
                    >
                        {{ settingsItem.title }}
                    </TooltipContent>
                </Tooltip>

                <Tooltip :delay-duration="120">
                    <TooltipTrigger as-child>
                        <button
                            type="button"
                            class="app-sidebar-toggle orvion-side-chip inline-flex size-9 shrink-0 items-center justify-center rounded-full text-zinc-700"
                            :class="expanded ? 'ms-1 self-start is-expanded' : ''"
                            data-tour="sidebar-collapse"
                            :aria-label="
                                expanded
                                    ? t('Collapse sidebar')
                                    : t('Expand sidebar')
                            "
                            @click="onToggle"
                        >
                            <component
                                :is="ToggleIcon"
                                class="size-3.5 transition-transform duration-300 ease-[cubic-bezier(0.34,1.4,0.64,1)]"
                                stroke-width="2"
                            />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent
                        v-if="!expanded"
                        :side="tooltipSide"
                        :side-offset="2"
                        :avoid-collisions="false"
                        class="rounded-full border-0 bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white shadow-lg [&_[class*='fill-foreground']]:hidden"
                    >
                        {{ t('Expand sidebar') }}
                    </TooltipContent>
                </Tooltip>
            </div>
        </div>
    </aside>

    <Sheet :open="openMobile" @update:open="setOpenMobile">
        <SheetContent
            :side="sheetSide"
            class="w-[280px] border-0 bg-transparent p-3 [&>button]:hidden"
        >
            <SheetHeader class="sr-only">
                <SheetTitle>Sidebar</SheetTitle>
                <SheetDescription>Navigation</SheetDescription>
            </SheetHeader>
            <div class="orvion-side-panel flex h-full flex-col gap-2 p-4">
                <Link
                    :href="dashboard()"
                    class="mb-2 px-2 text-sm font-semibold text-foreground"
                    @click="onNavigate"
                >
                    {{ name }}
                </Link>

                <Link
                    v-for="(item, index) in allNavItems"
                    :key="`m-${String(item.href)}`"
                    :href="item.href"
                    class="app-sidebar-mobile-item inline-flex items-center gap-3 rounded-full px-1 py-0.5 text-sm font-medium"
                    :style="{ '--item-i': index }"
                    :class="
                        isItemActive(item)
                            ? 'text-zinc-900 dark:text-white'
                            : 'text-zinc-600 dark:text-zinc-300'
                    "
                    @click="onNavigate"
                >
                    <span
                        class="inline-flex size-11 items-center justify-center rounded-full transition-transform duration-300"
                        :class="
                            isItemActive(item)
                                ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                : 'orvion-side-chip'
                        "
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            class="size-4"
                            stroke-width="1.75"
                        />
                    </span>
                    {{ item.title }}
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
