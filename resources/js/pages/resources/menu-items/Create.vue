<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import SettingsPanel from '@/components/settings/SettingsPanel.vue';
import StoreSettingsTabs from '@/components/settings/StoreSettingsTabs.vue';
import { useTranslations } from '@/composables/useTranslations';
import MenuItemForm, {
    type MenuItemFormState,
} from '@/pages/resources/menu-items/MenuItemForm.vue';

const props = defineProps<{
    menus: Array<{ id: number; title: string }>;
    defaults?: {
        menu_id?: string | number | null;
        status?: boolean;
    };
}>();

const { t } = useTranslations();

const form = useForm<MenuItemFormState>({
    menu_id: props.defaults?.menu_id ? String(props.defaults.menu_id) : '',
    title: '',
    price: '',
    description: '',
    image: null,
    remove_image: false,
    status: props.defaults?.status ?? true,
    sizes: [],
    toppings: [],
});

function submit(): void {
    form
        .transform((data) => ({
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
        .post('/menu-items', {
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
            { title: 'Create', href: '/menu-items/create' },
        ],
    },
});
</script>

<template>
    <Head :title="t('Create Menu Item')" />

    <SettingsPage>
        <template #tabs>
            <StoreSettingsTabs />
        </template>

        <SettingsPanel>
            <MenuItemForm
                :form="form"
                :menus="menus"
                :title="t('Create menu item')"
                :description="t('Basics, pricing, sizes, and topping groups.')"
                cancel-href="/menu-items"
                @submit="submit"
            />
        </SettingsPanel>
    </SettingsPage>
</template>
