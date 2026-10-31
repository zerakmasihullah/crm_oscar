<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { dustOut, findDustRow } from '@/lib/dustOut';
import type { RowActionItem } from '@/lib/row-actions';

const props = withDefaults(
    defineProps<{
        actions: RowActionItem[];
        align?: 'start' | 'center' | 'end';
    }>(),
    { align: 'end' },
);

const { t } = useTranslations();

const rootEl = ref<HTMLElement | null>(null);
const visibleActions = computed(() =>
    props.actions.filter((action) => !action.hidden),
);

const pendingAction = ref<RowActionItem | null>(null);
const processing = ref(false);

const isLinkAction = (action: RowActionItem): boolean =>
    Boolean(
        action.href &&
            !action.method &&
            !action.onClick &&
            !action.confirm &&
            !action.download &&
            !action.openInNewTab,
    );

const isDownloadAction = (action: RowActionItem): boolean =>
    Boolean(action.href && (action.download || action.openInNewTab));

function handleClick(action: RowActionItem): void {
    if (action.confirm) {
        pendingAction.value = action;

        return;
    }

    void runAction(action);
}

async function runAction(action: RowActionItem): Promise<void> {
    processing.value = true;

    const shouldDust =
        action.dust === true ||
        (action.dust !== false && action.method === 'delete');

    if (shouldDust) {
        const row = findDustRow(rootEl.value);
        if (row) {
            // Close confirm dialog so the row is visible while dusting.
            pendingAction.value = null;
            await dustOut(row, { collapse: true, preset: 'row' });
        }
    }

    if (action.onClick) {
        action.onClick();
        processing.value = false;
        pendingAction.value = null;

        return;
    }

    if (!action.href || !action.method) {
        processing.value = false;

        return;
    }

    const payload = action.data ?? {};

    const options = {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pendingAction.value = null;
        },
    };

    switch (action.method) {
        case 'delete':
            router.delete(action.href, { ...options, data: payload });
            break;
        case 'post':
            router.post(action.href, payload, options);
            break;
        case 'put':
            router.put(action.href, payload, options);
            break;
        case 'patch':
            router.patch(action.href, payload, options);
            break;
    }
}

function confirmPendingAction(): void {
    if (pendingAction.value) {
        void runAction(pendingAction.value);
    }
}
</script>

<template>
    <div ref="rootEl" class="contents">
        <DropdownMenu v-if="visibleActions.length > 0">
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" size="icon" class="size-8">
                    <MoreHorizontal class="size-4" />
                    <span class="sr-only">{{ t('Actions') }}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent :align="align" class="w-48">
                <template
                    v-for="(action, index) in visibleActions"
                    :key="`${action.label}-${index}`"
                >
                    <DropdownMenuSeparator
                        v-if="action.separator && index > 0"
                    />
                    <DropdownMenuItem
                        v-if="isDownloadAction(action)"
                        :variant="action.variant"
                        :disabled="action.disabled"
                        as-child
                    >
                        <a
                            v-if="action.href"
                            :href="action.href"
                            :target="action.openInNewTab ? '_blank' : undefined"
                            :rel="action.openInNewTab ? 'noopener' : undefined"
                        >
                            <component :is="action.icon" v-if="action.icon" />
                            {{ action.label }}
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else-if="isLinkAction(action)"
                        :variant="action.variant"
                        :disabled="action.disabled"
                        as-child
                    >
                        <Link v-if="action.href" :href="action.href">
                            <component :is="action.icon" v-if="action.icon" />
                            {{ action.label }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else
                        :variant="action.variant"
                        :disabled="action.disabled"
                        @click="handleClick(action)"
                    >
                        <component :is="action.icon" v-if="action.icon" />
                        {{ action.label }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <Dialog
            :open="pendingAction !== null"
            @update:open="(open) => !open && (pendingAction = null)"
        >
            <DialogContent v-if="pendingAction?.confirm">
                <DialogHeader>
                    <DialogTitle>{{ pendingAction.confirm.title }}</DialogTitle>
                    <DialogDescription>
                        {{ pendingAction.confirm.description }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" :disabled="processing">
                            {{ t('Cancel') }}
                        </Button>
                    </DialogClose>
                    <Button
                        :variant="pendingAction.confirmVariant ?? 'destructive'"
                        :disabled="processing"
                        @click="confirmPendingAction"
                    >
                        {{
                            processing
                                ? t('Processing...')
                                : (pendingAction.confirm.confirmLabel ??
                                  t('Confirm'))
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
