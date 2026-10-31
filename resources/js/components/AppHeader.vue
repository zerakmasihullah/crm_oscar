<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AiHeaderButton from '@/components/AiHeaderButton.vue';
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
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useAppNavigation } from '@/composables/useAppNavigation';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useInitials } from '@/composables/useInitials';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
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
const { isCurrentOrParentUrl } = useCurrentUrl();
const { allNavItems, headerTabItems } = useAppNavigation();

const user = computed(() => page.props.auth.user);
const name = computed(() => page.props.name as string);
const logo = computed(() => page.props.logo as string | null | undefined);
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

function isNavItemActive(item: { title: string; href: unknown }): boolean {
    if (item.title === t('Settings')) {
        return isStoreSettingsPath();
    }

    return isCurrentOrParentUrl(item.href as string);
}
</script>

<template>
    <header
        class="sticky top-0 z-30 bg-app-shell/80 backdrop-blur-xl"
        data-tour="app-header-nav"
    >
        <div class="relative mx-auto flex h-[4.5rem] w-full max-w-[90rem] items-center gap-3 px-4 md:px-6">
            <div class="lg:hidden">
                <Sheet>
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" class="size-10 rounded-full">
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[300px] border-0 bg-app-shell p-0">
                        <SheetTitle class="sr-only">{{ t('Navigation') }}</SheetTitle>
                        <SheetHeader class="p-4 text-start">
                            <Link :href="dashboard()" class="flex items-center gap-2">
                                <span
                                    class="inline-flex size-9 items-center justify-center rounded-full bg-foreground text-background"
                                >
                                    <AppLogoIcon class="size-4 fill-current" />
                                </span>
                                <span class="font-semibold">{{ name }}</span>
                            </Link>
                        </SheetHeader>
                        <nav class="space-y-1 px-3 pb-4">
                            <Link
                                v-for="item in allNavItems"
                                :key="toUrl(item.href)"
                                :href="item.href"
                                class="flex items-center gap-3 rounded-full px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    isNavItemActive(item)
                                        ? 'bg-foreground text-background'
                                        : 'text-foreground/80 hover:bg-white dark:hover:bg-card'
                                "
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    class="size-4 shrink-0"
                                />
                                {{ item.title }}
                            </Link>
                        </nav>
                    </SheetContent>
                </Sheet>
            </div>

            <Link :href="dashboard()" class="hidden min-w-0 items-center gap-2.5 lg:flex">
                <span
                    class="inline-flex size-9 items-center justify-center overflow-hidden rounded-full bg-foreground text-background"
                >
                    <img
                        v-if="logo"
                        :src="logo"
                        :alt="name"
                        class="size-full object-contain p-1"
                    />
                    <AppLogoIcon v-else class="size-4 fill-current text-background" />
                </span>
                <span class="truncate text-lg font-semibold tracking-tight">{{ name }}</span>
            </Link>

            <nav
                v-if="headerTabItems.length"
                class="absolute start-1/2 top-1/2 hidden -translate-x-1/2 -translate-y-1/2 rtl:translate-x-1/2 lg:flex"
                aria-label="Section"
            >
                <div
                    class="flex items-center gap-1 rounded-full bg-white/80 p-1.5 shadow-[0_10px_40px_-20px_rgba(15,23,42,0.35)] ring-1 ring-black/5 backdrop-blur-xl dark:bg-card/80 dark:ring-white/10"
                >
                    <Link
                        v-for="item in headerTabItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium transition"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            class="size-4 shrink-0"
                            stroke-width="1.75"
                        />
                        {{ item.title }}
                    </Link>
                </div>
            </nav>

            <div class="ms-auto flex shrink-0 items-center gap-2">
                <NewOrderHeaderButton />
                <div
                    class="inline-flex items-center gap-0.5 rounded-full bg-white/80 p-1 shadow-[0_10px_40px_-20px_rgba(15,23,42,0.35)] ring-1 ring-black/5 backdrop-blur-xl dark:bg-card/80 dark:ring-white/10"
                >
                    <GlobalSearch />
                    <LanguageSwitcher />
                    <ChatHeaderButton />
                    <AiHeaderButton />
                    <NotificationBell />
                </div>
                <DropdownMenu v-if="user">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="size-10 rounded-full p-0 hover:bg-white/70 dark:hover:bg-white/10"
                        >
                            <Avatar class="size-10">
                                <AvatarImage
                                    v-if="showAvatar"
                                    :src="user.avatar!"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="bg-foreground text-xs font-semibold text-background"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="min-w-56 rounded-2xl border-border/60 p-1.5"
                    >
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
