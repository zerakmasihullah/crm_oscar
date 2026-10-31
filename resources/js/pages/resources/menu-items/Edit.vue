<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ExistingUploadFile } from '@/components/FileUploadField.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';
import MenuItemForm, {
    type MenuItemFormState,
    type SizeRow,
    type ToppingRow,
} from '@/pages/resources/menu-items/MenuItemForm.vue';

type MenuItemPayload = {
    id: number;
    menu_id: number;
    title: string;
    price?: number | null;
    description?: string | null;
    image?: string | null;
    image_url?: string | null;
    status: boolean;
    sizes?: SizeRow[];
    toppings?: ToppingRow[];
};

const props = defineProps<{
    item: MenuItemPayload;
    menus: Array<{ id: number; title: string }>;
}>();

const { t } = useTranslations();

const form = useForm<MenuItemFormState>({
    menu_id: String(props.item.menu_id ?? ''),
    title: props.item.title ?? '',
    price: props.item.price ?? '',
    description: props.item.description ?? '',
    image: null,
    remove_image: false,
    status: Boolean(props.item.status),
    sizes: (props.item.sizes ?? []).map((size) => ({
        id: size.id,
        size: size.size,
        price: size.price,
    })),
    toppings: (props.item.toppings ?? []).map((topping) => ({
        id: topping.id,
        name: topping.name,
        required: topping.required ?? 0,
        is_optional: Boolean(topping.is_optional),
        attachments: (topping.attachments ?? []).map((attachment) => ({
            id: attachment.id,
            name: attachment.name,
            price: attachment.price,
        })),
    })),
});

const existingImage = computed<ExistingUploadFile | null>(() => {
    if (!props.item.image_url && !props.item.image) {
        return null;
    }

    return {
        name: props.item.image?.split('/').pop() || t('Image'),
        url: props.item.image_url ?? undefined,
        path: props.item.image ?? undefined,
    };
});

function submit(): void {
    form
        .transform((data) => ({
            _method: 'put',
            menu_id: data.menu_id,
            title: data.title,
            price: data.price === '' || data.price === null ? null : data.price,
            description: data.description,
            image: data.image,
            status: data.status ? 1 : 0,
            remove_image: data.remove_image ? 1 : 0,
            sizes: data.sizes.map((size) => ({
                ...(size.id ? { id: size.id } : {}),
                size: size.size,
                price: size.price,
            })),
            toppings: data.toppings.map((topping) => ({
                ...(topping.id ? { id: topping.id } : {}),
                name: topping.name,
                required: topping.required ?? 0,
                is_optional: topping.is_optional ? 1 : 0,
                attachments: topping.attachments.map((attachment) => ({
                    ...(attachment.id ? { id: attachment.id } : {}),
                    name: attachment.name,
                    price: attachment.price,
                })),
            })),
        }))
        .post(`/menu-items/${props.item.id}`, {
            forceFormData: true,
            onFinish: () => {
                form.transform((data) => data);
            },
        });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Menu Items', href: '/menu-items' },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Edit Menu Item')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MenuItemForm
                :form="form"
                :menus="menus"
                :title="t('Edit menu item')"
                :description="t('Update this storefront item.')"
                :cancel-href="`/menu-items/${item.id}`"
                :existing-image="existingImage"
                @submit="submit"
            />
        </SettingsPanel>
    </SettingsPage>
</template>
