<script setup lang="ts">
import {
    CalendarDays,
    ShoppingBag,
    Store,
    Truck,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed } from 'vue';
import { MisTabs } from '@/components/mis';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const { can } = usePermissions();
const { currentUrl } = useCurrentUrl();

const tabs = computed(() =>
    [
        {
            id: 'menus',
            href: '/menus',
            label: t('Menus'),
            icon: UtensilsCrossed,
            show: can('menus.view'),
        },
        {
            id: 'menu-items',
            href: '/menu-items',
            label: t('Menu items'),
            icon: ShoppingBag,
            show: can('menu_items.view'),
        },
        {
            id: 'deliveries',
            href: '/deliveries',
            label: t('Delivery'),
            icon: Truck,
            show: can('deliveries.view'),
        },
        {
            id: 'reservations',
            href: '/reservations',
            label: t('Reservations'),
            icon: CalendarDays,
            show: can('reservations.view'),
        },
        {
            id: 'platforms',
            href: '/settings/platforms',
            label: t('Sales platforms'),
            icon: Store,
            show: can('platforms.view'),
        },
    ]
        .filter((tab) => tab.show)
        .map(({ show: _show, ...tab }) => tab),
);

const active = computed(() => {
    const path = currentUrl.value;
    if (path.startsWith('/menu-items')) return 'menu-items';
    if (path.startsWith('/menus')) return 'menus';
    if (path.startsWith('/deliveries')) return 'deliveries';
    if (path.startsWith('/reservations')) return 'reservations';
    if (path.startsWith('/settings/platforms')) return 'platforms';
    return tabs.value[0]?.id ?? 'menus';
});

const model = computed({
    get: () => active.value,
    set: () => undefined,
});
</script>

<template>
    <MisTabs v-if="tabs.length > 1" v-model="model" :tabs="tabs" class="mb-0" />
</template>
