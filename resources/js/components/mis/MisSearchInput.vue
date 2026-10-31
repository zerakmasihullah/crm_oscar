<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const model = defineModel<string>({ default: '' });

withDefaults(
    defineProps<{
        id?: string;
        placeholder?: string;
        ariaLabel?: string;
        clearable?: boolean;
        disabled?: boolean;
        class?: string;
        inputClass?: string;
    }>(),
    {
        id: undefined,
        placeholder: undefined,
        ariaLabel: 'Search',
        clearable: true,
        disabled: false,
        class: undefined,
        inputClass: undefined,
    },
);

const emit = defineEmits<{
    clear: [];
    submit: [];
}>();

function clear(): void {
    model.value = '';
    emit('clear');
}
</script>

<template>
    <div :class="cn('relative', $props.class)">
        <Search
            class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            :id="id"
            v-model="model"
            type="search"
            :disabled="disabled"
            :aria-label="ariaLabel"
            :placeholder="placeholder"
            :class="
                cn(
                    'ps-9',
                    clearable && model ? 'pe-9' : '',
                    inputClass,
                )
            "
            @keydown.enter.prevent="emit('submit')"
        />
        <button
            v-if="clearable && model"
            type="button"
            class="absolute end-2.5 top-1/2 inline-flex size-5 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
            :aria-label="'Clear'"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
