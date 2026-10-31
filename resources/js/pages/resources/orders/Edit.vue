<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    MisForm,
    MisInputField,
    MisPage,
    MisPageHeader,
    MisSelectField,
    MisTextareaField,
} from '@/components/mis';
import { useTranslations } from '@/composables/useTranslations';
import { pageBreadcrumbs } from '@/lib/pageBreadcrumbs';

type Size = { id: number; size: string; price: number };
type MenuItemOpt = { id: number; title: string; price: number; sizes: Size[] };
type MenuOpt = { id: number; title: string; items: MenuItemOpt[] };

type Line = {
    menu_id: string;
    menu_item_id: string;
    size_id: string;
    quantity: number;
    price: number;
};

const props = defineProps<{
    item: {
        id: number;
        customer_id: number | null;
        customer_name: string | null;
        phone_number: string | null;
        address: string | null;
        status: number;
        type: number;
        discount: number | string | null;
        payment_method: string;
        deliveryman_id: number | null;
        notes_text: string;
        order_items: {
            menu_id?: number | null;
            menu_item_id: number | null;
            size_id: number | null;
            quantity: number;
            price: number;
        }[];
    };
    menus: MenuOpt[];
    customers: { id: number; name: string; phone_number?: string }[];
    deliverymen: { id: number; name: string }[];
    statusOptions: Record<string, string>;
    typeOptions: Record<string, string>;
}>();

const { t } = useTranslations();

function toLine(item: (typeof props.item.order_items)[number]): Line {
    return {
        menu_id: item.menu_id ? String(item.menu_id) : '',
        menu_item_id: item.menu_item_id ? String(item.menu_item_id) : '',
        size_id: item.size_id ? String(item.size_id) : '',
        quantity: item.quantity ?? 1,
        price: Number(item.price ?? 0),
    };
}

const form = useForm({
    customer_id: props.item.customer_id ? String(props.item.customer_id) : '',
    customer_name: props.item.customer_name ?? '',
    phone_number: props.item.phone_number ?? '',
    address: props.item.address ?? '',
    status: String(props.item.status),
    type: String(props.item.type),
    discount: Number(props.item.discount ?? 0),
    payment_method: props.item.payment_method || 'cash',
    deliveryman_id: props.item.deliveryman_id
        ? String(props.item.deliveryman_id)
        : '',
    description: props.item.notes_text ?? '',
    order_items: (props.item.order_items?.length
        ? props.item.order_items.map(toLine)
        : [
              {
                  menu_id: '',
                  menu_item_id: '',
                  size_id: '',
                  quantity: 1,
                  price: 0,
              },
          ]) as Line[],
});

const pricingHint = ref('');

const customerOptions = computed(() =>
    props.customers.map((c) => ({
        value: String(c.id),
        label: c.name + (c.phone_number ? ` (${c.phone_number})` : ''),
    })),
);

function menuItemsFor(line: Line): MenuItemOpt[] {
    return props.menus.find((m) => m.id === Number(line.menu_id))?.items ?? [];
}

function sizesFor(line: Line): Size[] {
    return (
        menuItemsFor(line).find((i) => i.id === Number(line.menu_item_id))
            ?.sizes ?? []
    );
}

function syncLinePrice(line: Line) {
    const sizes = sizesFor(line);
    if (line.size_id) {
        line.price = sizes.find((s) => s.id === Number(line.size_id))?.price ?? 0;
        return;
    }
    line.price =
        menuItemsFor(line).find((i) => i.id === Number(line.menu_item_id))
            ?.price ?? 0;
}

async function refreshPricing() {
    const items = form.order_items
        .filter((i) => i.menu_item_id)
        .map((i) => ({
            menu_item_id: Number(i.menu_item_id),
            size_id: i.size_id ? Number(i.size_id) : null,
            quantity: i.quantity,
            price: i.price,
        }));
    if (!items.length) {
        pricingHint.value = '';
        return;
    }
    try {
        const res = await fetch('/orders/pricing-preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                order_items: items,
                type: Number(form.type),
                discount: form.discount,
            }),
        });
        if (!res.ok) return;
        const data = await res.json();
        pricingHint.value = data.hint ?? '';
    } catch {
        /* ignore */
    }
}

watch(
    () => [form.order_items, form.type, form.discount],
    () => refreshPricing(),
    { deep: true, immediate: true },
);

function addLine() {
    form.order_items.push({
        menu_id: '',
        menu_item_id: '',
        size_id: '',
        quantity: 1,
        price: 0,
    });
}

function removeLine(index: number) {
    form.order_items.splice(index, 1);
}

function submit() {
    form
        .transform((data) => ({
            customer_id: data.customer_id ? Number(data.customer_id) : null,
            customer_name: data.customer_name || null,
            phone_number: data.phone_number || null,
            address: data.address || null,
            status: Number(data.status),
            type: Number(data.type),
            discount: data.discount,
            payment_method: data.payment_method,
            deliveryman_id: data.deliveryman_id
                ? Number(data.deliveryman_id)
                : null,
            description: data.description || null,
            order_items: data.order_items.map((item) => ({
                menu_item_id: Number(item.menu_item_id),
                size_id: item.size_id ? Number(item.size_id) : null,
                quantity: item.quantity,
                price: item.price,
            })),
        }))
        .put(`/orders/${props.item.id}`);
}

defineOptions(
    pageBreadcrumbs(
        { title: 'Orders', href: '/orders' },
        { title: 'Edit', href: '/orders' },
    ),
);
</script>

<template>
    <Head :title="`${t('Edit order')} #${item.id}`" />

    <MisPage narrow>
        <MisPageHeader :title="`${t('Edit order')} #${item.id}`" />

        <MisForm
            :processing="form.processing"
            :errors="form.errors"
            :cancel-href="`/orders/${item.id}`"
            :submit-label="t('Save')"
            max-width="2xl"
            @submit="submit"
        >
            <div
                v-for="(line, index) in form.order_items"
                :key="index"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">
                        {{ t('Item') }} {{ index + 1 }}
                    </h3>
                    <Button
                        v-if="form.order_items.length > 1"
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="removeLine(index)"
                    >
                        {{ t('Remove') }}
                    </Button>
                </div>

                <MisSelectField
                    :id="`menu-${index}`"
                    v-model="line.menu_id"
                    :label="t('Menu')"
                    :options="
                        menus.map((m) => ({
                            value: String(m.id),
                            label: m.title,
                        }))
                    "
                    required
                    @update:model-value="
                        () => {
                            line.menu_item_id = '';
                            line.size_id = '';
                            line.price = 0;
                        }
                    "
                />

                <MisSelectField
                    :id="`item-${index}`"
                    v-model="line.menu_item_id"
                    :label="t('Menu item')"
                    :options="
                        menuItemsFor(line).map((i) => ({
                            value: String(i.id),
                            label: `${i.title} (€${i.price})`,
                        }))
                    "
                    required
                    @update:model-value="
                        () => {
                            line.size_id = '';
                            syncLinePrice(line);
                        }
                    "
                />

                <MisSelectField
                    v-if="sizesFor(line).length"
                    :id="`size-${index}`"
                    v-model="line.size_id"
                    :label="t('Size')"
                    :options="
                        sizesFor(line).map((s) => ({
                            value: String(s.id),
                            label: `${s.size} (€${s.price})`,
                        }))
                    "
                    required
                    @update:model-value="() => syncLinePrice(line)"
                />

                <MisInputField
                    :id="`qty-${index}`"
                    v-model.number="line.quantity"
                    type="number"
                    :label="t('Quantity')"
                    :min="1"
                    required
                />
            </div>

            <Button type="button" variant="outline" @click="addLine">
                {{ t('Add item') }}
            </Button>

            <MisSelectField
                id="customer_id"
                v-model="form.customer_id"
                :label="t('Customer')"
                :options="customerOptions"
            />
            <MisInputField
                id="customer_name"
                v-model="form.customer_name"
                :label="t('Customer name')"
            />
            <MisInputField
                id="phone_number"
                v-model="form.phone_number"
                :label="t('Phone')"
            />
            <MisInputField
                id="address"
                v-model="form.address"
                :label="t('Address')"
            />

            <MisSelectField
                id="type"
                v-model="form.type"
                :label="t('Type')"
                :options="
                    Object.entries(typeOptions).map(([value, label]) => ({
                        value,
                        label,
                    }))
                "
                required
            />

            <MisSelectField
                id="status"
                v-model="form.status"
                :label="t('Status')"
                :options="
                    Object.entries(statusOptions).map(([value, label]) => ({
                        value,
                        label,
                    }))
                "
                required
            />

            <MisSelectField
                id="payment_method"
                v-model="form.payment_method"
                :label="t('Payment')"
                :options="[
                    { value: 'cash', label: t('Cash') },
                    { value: 'card', label: t('Card') },
                    { value: 'stripe', label: t('Stripe') },
                ]"
                required
            />

            <MisSelectField
                v-if="form.type === '1'"
                id="deliveryman_id"
                v-model="form.deliveryman_id"
                :label="t('Driver')"
                :options="
                    deliverymen.map((d) => ({
                        value: String(d.id),
                        label: d.name,
                    }))
                "
            />

            <MisInputField
                id="discount"
                v-model.number="form.discount"
                type="number"
                :label="t('Discount')"
                :min="0"
                step="0.01"
            />

            <MisTextareaField
                id="description"
                v-model="form.description"
                :label="t('Notes')"
                :rows="3"
            />

            <p v-if="pricingHint" class="text-sm text-muted-foreground">
                {{ pricingHint }}
            </p>
        </MisForm>
    </MisPage>
</template>
