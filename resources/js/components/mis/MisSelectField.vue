<script setup lang="ts">
import { computed } from 'vue';
import MisField from '@/components/mis/MisField.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type MisSelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

const EMPTY_VALUE = '__mis_empty__';

const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        id?: string;
        label?: string;
        hint?: string;
        error?: string;
        options: MisSelectOption[];
        placeholder?: string;
        disabled?: boolean;
        class?: string;
        selectClass?: string;
        required?: boolean;
    }>(),
    {
        id: undefined,
        label: undefined,
        hint: undefined,
        error: undefined,
        placeholder: undefined,
        disabled: false,
        class: undefined,
        selectClass: undefined,
        required: false,
    },
);

const selectModel = computed(() =>
    model.value === '' ? EMPTY_VALUE : model.value,
);

/** Reka SelectItem rejects empty-string values — map them to a sentinel. */
const normalizedOptions = computed(() =>
    props.options.map((option) => ({
        ...option,
        value: option.value === '' ? EMPTY_VALUE : option.value,
    })),
);

function onUpdate(value: unknown): void {
    if (value == null || value === EMPTY_VALUE) {
        model.value = '';
        return;
    }
    model.value = String(value);
}
</script>

<template>
    <MisField
        :label="label"
        :html-for="id"
        :hint="hint"
        :error="error"
        :required="required"
        :class="props.class"
    >
        <Select
            :model-value="selectModel"
            :disabled="disabled"
            :required="required"
            @update:model-value="onUpdate"
        >
            <SelectTrigger :id="id" :class="cn('w-full', selectClass)">
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>
            <SelectContent class="z-[80]">
                <SelectItem
                    v-if="placeholder !== undefined"
                    :value="EMPTY_VALUE"
                >
                    {{ placeholder }}
                </SelectItem>
                <SelectItem
                    v-for="option in normalizedOptions"
                    :key="option.value"
                    :value="option.value"
                    :disabled="option.disabled"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>
    </MisField>
</template>
