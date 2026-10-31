<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Monitor, Moon, Settings, Sun } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';
import { onLogout } from '@/offline/lifecycle';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { Appearance } from '@/types';
import type { User } from '@/types';

type Props = {
    user: User;
};

const props = defineProps<Props>();
const { t } = useTranslations();
const { appearance, updateAppearance } = useAppearance();

const modes: Array<{ value: Appearance; icon: typeof Sun; label: string }> = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

const activeMode = computed(() => appearance.value);

const handleLogout = () => {
    void onLogout(props.user.id);
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2.5 px-2 py-1.5">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator class="my-1" />

    <div class="px-1.5 py-1">
        <div class="grid grid-cols-3 gap-0.5 rounded-lg bg-muted/70 p-0.5">
            <button
                v-for="mode in modes"
                :key="mode.value"
                type="button"
                class="inline-flex h-8 items-center justify-center rounded-md transition"
                :class="
                    activeMode === mode.value
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-label="t(mode.label)"
                :title="t(mode.label)"
                @click="updateAppearance(mode.value)"
            >
                <component :is="mode.icon" class="size-3.5" stroke-width="1.75" />
            </button>
        </div>
    </div>

    <DropdownMenuSeparator class="my-1" />

    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true" class="cursor-pointer rounded-lg px-2 py-1.5">
            <Link class="flex w-full items-center gap-2" :href="edit()" prefetch>
                <Settings class="size-4" stroke-width="1.75" />
                {{ t('Settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuItem :as-child="true" class="cursor-pointer rounded-lg px-2 py-1.5">
        <Link
            class="flex w-full items-center gap-2"
            :href="logout()"
            as="button"
            data-test="logout-button"
            @click="handleLogout"
        >
            <LogOut class="size-4" stroke-width="1.75" />
            {{ t('Log out') }}
        </Link>
    </DropdownMenuItem>
</template>
