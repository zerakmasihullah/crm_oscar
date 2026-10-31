<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

const props = defineProps<{
    breadcrumbs: BreadcrumbItemType[];
    class?: HTMLAttributes['class'];
}>();

const { t } = useTranslations();
</script>

<template>
    <Breadcrumb :class="cn(props.class)">
        <BreadcrumbList class="gap-1 text-xs sm:gap-1.5">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem class="gap-1">
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="text-xs font-medium">
                            {{ t(item.title) }}
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child class="text-xs">
                            <Link :href="item.href">{{ t(item.title) }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="[&>svg]:size-3"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
