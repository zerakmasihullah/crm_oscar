<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { Layers, Plus, ShoppingBag, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import FileUploadField, {
    type ExistingUploadFile,
} from '@/components/FileUploadField.vue';
import {
    MisForm,
    MisInputField,
    MisSection,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import SettingsToggleRow from '@/components/settings/SettingsToggleRow.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

export type SizeRow = {
    id?: number | null;
    size: string;
    price: string | number;
};

export type AttachmentRow = {
    id?: number | null;
    name: string;
    price: string | number;
};

export type ToppingRow = {
    id?: number | null;
    name: string;
    required: string | number;
    is_optional: boolean;
    attachments: AttachmentRow[];
};

export type MenuItemFormState = {
    menu_id: string;
    title: string;
    price: string | number | null;
    description: string;
    image: File | null;
    remove_image: boolean;
    status: boolean;
    sizes: SizeRow[];
    toppings: ToppingRow[];
};

const props = defineProps<{
    form: InertiaForm<MenuItemFormState>;
    menus: Array<{ id: number; title: string }>;
    cancelHref: string;
    title?: string;
    description?: string;
    existingImage?: ExistingUploadFile | null;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslations();

const menuOptions = computed(() =>
    props.menus.map((menu) => ({
        value: String(menu.id),
        label: menu.title,
    })),
);

function onImageDeferred(files: File[]): void {
    props.form.image = files[0] ?? null;
    props.form.remove_image = false;
}

function onImageRemoved(): void {
    props.form.image = null;
    props.form.remove_image = true;
}

function addSize(): void {
    props.form.sizes = [...props.form.sizes, { size: '', price: '' }];
}

function removeSize(index: number): void {
    props.form.sizes = props.form.sizes.filter((_, i) => i !== index);
}

function addTopping(): void {
    props.form.toppings = [
        ...props.form.toppings,
        {
            name: '',
            required: 0,
            is_optional: true,
            attachments: [{ name: '', price: '' }],
        },
    ];
}

function removeTopping(index: number): void {
    props.form.toppings = props.form.toppings.filter((_, i) => i !== index);
}

function addAttachment(toppingIndex: number): void {
    const toppings = [...props.form.toppings];
    toppings[toppingIndex] = {
        ...toppings[toppingIndex],
        attachments: [
            ...toppings[toppingIndex].attachments,
            { name: '', price: '' },
        ],
    };
    props.form.toppings = toppings;
}

function removeAttachment(toppingIndex: number, attachmentIndex: number): void {
    const toppings = [...props.form.toppings];
    toppings[toppingIndex] = {
        ...toppings[toppingIndex],
        attachments: toppings[toppingIndex].attachments.filter(
            (_, i) => i !== attachmentIndex,
        ),
    };
    props.form.toppings = toppings;
}
</script>

<template>
    <MisForm
        :processing="form.processing"
        :errors="form.errors"
        show-error-summary
        :cancel-href="cancelHref"
        :submit-label="t('Save item')"
        max-width="full"
        fields-class="gap-8"
        @submit="emit('submit')"
    >
        <MisSection
            :title="title || t('Menu item')"
            :description="
                description || t('Basics, pricing, sizes, and topping groups.')
            "
            :icon="ShoppingBag"
        >
            <div class="grid gap-6 lg:grid-cols-[12rem_minmax(0,1fr)]">
                <FileUploadField
                    mode="deferred"
                    accept="image/*"
                    preview="image"
                    :label="t('Image')"
                    :hint="t('PNG or JPG up to 5MB.')"
                    :max-bytes="5 * 1024 * 1024"
                    :existing="existingImage"
                    :error="form.errors.image"
                    @update:deferred="onImageDeferred"
                    @remove="onImageRemoved"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <MisSelectField
                        id="menu_id"
                        v-model="form.menu_id"
                        :label="t('Menu')"
                        :options="menuOptions"
                        :placeholder="t('Select menu')"
                        :error="form.errors.menu_id"
                        required
                    />
                    <MisInputField
                        id="title"
                        v-model="form.title"
                        :label="t('Title')"
                        :placeholder="t('e.g. Classic Burger')"
                        :error="form.errors.title"
                        required
                    />
                    <MisInputField
                        id="price"
                        v-model="form.price"
                        type="number"
                        prefix="€"
                        :label="t('Base price')"
                        :hint="t('Used when no sizes are added.')"
                        :error="form.errors.price"
                        step="0.01"
                        min="0"
                    />
                    <div class="sm:col-span-2 rounded-xl border border-border/70 px-4">
                        <SettingsToggleRow
                            :title="t('Active')"
                            :description="t('Inactive items stay hidden on the storefront.')"
                            :model-value="form.status"
                            @update:model-value="(value) => (form.status = value)"
                        />
                    </div>
                    <MisTextareaField
                        id="description"
                        v-model="form.description"
                        class="sm:col-span-2"
                        :label="t('Description')"
                        :error="form.errors.description"
                        :rows="4"
                    />
                </div>
            </div>
        </MisSection>

        <MisSection
            :title="t('Sizes')"
            :description="t('Leave empty if the item has a single price.')"
            :icon="Layers"
        >
            <template #actions>
                <Button type="button" size="sm" variant="outline" @click="addSize">
                    <Plus class="size-4" />
                    {{ t('Add size') }}
                </Button>
            </template>

            <div
                v-if="!form.sizes.length"
                class="rounded-xl border border-dashed border-border/80 px-4 py-8 text-center text-sm text-muted-foreground"
            >
                {{ t('No sizes yet.') }}
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="(size, index) in form.sizes"
                    :key="`size-${size.id ?? index}`"
                    class="grid items-end gap-3 rounded-xl border border-border/60 bg-muted/20 p-3 sm:grid-cols-[1fr_9rem_auto]"
                >
                    <MisInputField
                        :id="`size-name-${index}`"
                        v-model="size.size"
                        :label="t('Size')"
                        :placeholder="t('e.g. Large')"
                        required
                    />
                    <MisInputField
                        :id="`size-price-${index}`"
                        v-model="size.price"
                        type="number"
                        prefix="€"
                        step="0.01"
                        min="0"
                        :label="t('Price')"
                        required
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        class="mb-0.5 text-muted-foreground hover:text-destructive"
                        @click="removeSize(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>
        </MisSection>

        <MisSection
            :title="t('Toppings')"
            :description="t('Option groups with priced choices.')"
            :icon="Layers"
        >
            <template #actions>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="addTopping"
                >
                    <Plus class="size-4" />
                    {{ t('Add topping') }}
                </Button>
            </template>

            <div
                v-if="!form.toppings.length"
                class="rounded-xl border border-dashed border-border/80 px-4 py-8 text-center text-sm text-muted-foreground"
            >
                {{ t('No toppings yet.') }}
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="(topping, toppingIndex) in form.toppings"
                    :key="`topping-${topping.id ?? toppingIndex}`"
                    class="space-y-4 rounded-2xl border border-border/70 bg-card p-4 shadow-soft"
                >
                    <div
                        class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_auto]"
                    >
                        <MisInputField
                            :id="`topping-name-${toppingIndex}`"
                            v-model="topping.name"
                            :label="t('Group name')"
                            :placeholder="t('e.g. Extra toppings')"
                            required
                        />
                        <MisInputField
                            :id="`topping-required-${toppingIndex}`"
                            v-model="topping.required"
                            type="number"
                            min="0"
                            :label="t('Min')"
                            :hint="t('Required picks')"
                        />
                        <div class="flex items-end justify-end gap-1 pb-1">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="text-muted-foreground hover:text-destructive"
                                @click="removeTopping(toppingIndex)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-border/60 px-4"
                    >
                        <SettingsToggleRow
                            :title="t('Optional group')"
                            :description="t('Customers can skip this group.')"
                            :model-value="topping.is_optional"
                            @update:model-value="
                                (value) => (topping.is_optional = value)
                            "
                        />
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <Label class="text-sm font-medium">{{
                                t('Choices')
                            }}</Label>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="addAttachment(toppingIndex)"
                            >
                                <Plus class="size-3.5" />
                                {{ t('Add choice') }}
                            </Button>
                        </div>

                        <div
                            v-for="(
                                attachment, attachmentIndex
                            ) in topping.attachments"
                            :key="`attachment-${attachment.id ?? attachmentIndex}`"
                            class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_8rem_auto]"
                        >
                            <MisInputField
                                :id="`att-name-${toppingIndex}-${attachmentIndex}`"
                                v-model="attachment.name"
                                :label="
                                    attachmentIndex === 0 ? t('Name') : undefined
                                "
                                :placeholder="t('Name')"
                                required
                            />
                            <MisInputField
                                :id="`att-price-${toppingIndex}-${attachmentIndex}`"
                                v-model="attachment.price"
                                type="number"
                                prefix="€"
                                step="0.01"
                                min="0"
                                :label="
                                    attachmentIndex === 0
                                        ? t('Price')
                                        : undefined
                                "
                                required
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mb-0.5 text-muted-foreground hover:text-destructive"
                                @click="
                                    removeAttachment(
                                        toppingIndex,
                                        attachmentIndex,
                                    )
                                "
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </MisSection>
    </MisForm>
</template>
