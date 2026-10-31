<script setup lang="ts">
import type { SidebarProps } from '.'
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import SheetDescription from '@/components/ui/sheet/SheetDescription.vue'
import SheetHeader from '@/components/ui/sheet/SheetHeader.vue'
import SheetTitle from '@/components/ui/sheet/SheetTitle.vue'
import {
    SIDEBAR_MARGIN,
    SIDEBAR_WIDTH,
    SIDEBAR_WIDTH_ICON,
    SIDEBAR_WIDTH_MOBILE,
    useSidebar,
} from './utils'

defineOptions({
    inheritAttrs: false,
})

const props = withDefaults(defineProps<SidebarProps>(), {
    side: 'left',
    variant: 'sidebar',
    collapsible: 'offcanvas',
})

const { isMobile, state, openMobile, setOpenMobile } = useSidebar()

/** Floating island — leave this path alone; kit default. */
const isFloating = computed(() => props.variant === 'floating')

const spacerWidth = computed(() => {
    if (props.collapsible === 'offcanvas' && state.value === 'collapsed') {
        return '0px'
    }

    const sidebarWidth =
        state.value === 'collapsed' && props.collapsible === 'icon'
            ? SIDEBAR_WIDTH_ICON
            : SIDEBAR_WIDTH

    return `calc(${sidebarWidth} + ${SIDEBAR_MARGIN} * 2)`
})
</script>

<template>
    <div
        v-if="collapsible === 'none'"
        data-slot="sidebar"
        :class="
            cn(
                'bg-sidebar text-sidebar-foreground flex h-full w-(--sidebar-width) flex-col',
                props.class,
            )
        "
        v-bind="$attrs"
    >
        <slot />
    </div>

    <Sheet
        v-else-if="isMobile"
        :open="openMobile"
        v-bind="$attrs"
        @update:open="setOpenMobile"
    >
        <SheetContent
            data-sidebar="sidebar"
            data-slot="sidebar"
            data-mobile="true"
            :side="side"
            class="bg-sidebar text-sidebar-foreground w-(--sidebar-width) border-0 p-0 [&>button]:hidden"
            :style="{
                '--sidebar-width': SIDEBAR_WIDTH_MOBILE,
            }"
        >
            <SheetHeader class="sr-only">
                <SheetTitle>Sidebar</SheetTitle>
                <SheetDescription>Displays the mobile sidebar.</SheetDescription>
            </SheetHeader>
            <div class="flex h-full w-full flex-col">
                <slot />
            </div>
        </SheetContent>
    </Sheet>

    <!-- Floating island (unchanged) -->
    <div
        v-else-if="isFloating"
        :class="cn('group peer hidden shrink-0 md:block', props.class)"
        data-slot="sidebar"
        :data-state="state"
        :data-collapsible="state === 'collapsed' ? collapsible : ''"
        :data-variant="variant"
        :data-side="side"
        :style="{ width: spacerWidth, flexShrink: 0 }"
    >
        <div
            class="relative shrink-0 bg-transparent transition-[width] duration-300 ease-in-out group-data-[collapsible=offcanvas]:w-0"
            :style="{ width: spacerWidth }"
        />
        <div
            :class="
                cn(
                    'fixed z-10 hidden h-[calc(100svh-(var(--sidebar-margin)*2))] transition-[width,left,right] duration-300 ease-in-out md:flex',
                    'top-[var(--sidebar-margin)]',
                    side === 'left'
                        ? 'left-[var(--sidebar-margin)] group-data-[collapsible=offcanvas]:-left-[calc(var(--sidebar-width)+var(--sidebar-margin))]'
                        : 'right-[var(--sidebar-margin)] group-data-[collapsible=offcanvas]:-right-[calc(var(--sidebar-width)+var(--sidebar-margin))]',
                    'group-data-[collapsible=icon]:group-data-[state=collapsed]:w-(--sidebar-width-icon) w-(--sidebar-width)',
                )
            "
            :style="{ '--sidebar-margin': SIDEBAR_MARGIN }"
            v-bind="$attrs"
        >
            <div
                data-sidebar="sidebar"
                class="bg-sidebar flex h-full w-full flex-col overflow-hidden rounded-[var(--radius-shell)] shadow-[var(--shadow-soft-lg)] transition-[width] duration-300 ease-in-out"
            >
                <slot />
            </div>
        </div>
    </div>

    <!-- Inset + flush: clean full-height rail (not the floating island) -->
    <div
        v-else
        :class="cn('group peer hidden shrink-0 md:block', props.class)"
        data-slot="sidebar"
        :data-state="state"
        :data-collapsible="state === 'collapsed' ? collapsible : ''"
        :data-variant="variant"
        :data-side="side"
    >
        <div
            class="relative w-(--sidebar-width) shrink-0 bg-transparent transition-[width] duration-300 ease-in-out group-data-[collapsible=icon]:w-(--sidebar-width-icon) group-data-[collapsible=offcanvas]:w-0"
        />
        <div
            :class="
                cn(
                    'fixed inset-y-0 z-10 hidden h-svh w-(--sidebar-width) transition-[width,left,right] duration-300 ease-in-out md:flex',
                    'group-data-[collapsible=icon]:w-(--sidebar-width-icon)',
                    side === 'left'
                        ? 'left-0 group-data-[collapsible=offcanvas]:-left-[calc(var(--sidebar-width))]'
                        : 'right-0 group-data-[collapsible=offcanvas]:-right-[calc(var(--sidebar-width))]',
                )
            "
            v-bind="$attrs"
        >
            <div
                data-sidebar="sidebar"
                :class="
                    cn(
                        'bg-sidebar flex h-full w-full flex-col overflow-hidden transition-[width] duration-300 ease-in-out',
                        variant === 'inset' && 'shell-sidebar-inset',
                        variant === 'sidebar' && 'shell-sidebar-flush',
                    )
                "
            >
                <slot />
            </div>
        </div>
    </div>
</template>
