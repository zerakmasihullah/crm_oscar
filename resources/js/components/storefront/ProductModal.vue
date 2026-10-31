<script setup lang="ts">
import { ChevronLeft, Info, Minus, Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';

function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    try {
        const htmlStr = String(html);
        const tmp = document.createElement('div');
        tmp.innerHTML = htmlStr;
        let text = tmp.textContent || tmp.innerText || '';
        text = text
            .replace(/&nbsp;/g, ' ')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'")
            .replace(/&apos;/g, "'")
            .replace(/\s+/g, ' ')
            .trim();
        return text;
    } catch {
        return String(html || '');
    }
}

interface OptionGroup {
    id: string;
    title: string;
    type: 'radio' | 'multi';
    required?: boolean;
    maxSelect?: number;
    options: { id: number; name: string; price: number }[];
}

interface Topping {
    id: number;
    name: string;
    required: number;
    is_optional: boolean;
    attachments?: Array<{ id: number; name: string; price: string | number }>;
}

interface Product {
    id: number;
    name?: string;
    title?: string;
    description?: string;
    price: string | number;
    image?: string;
    optionGroups?: OptionGroup[];
    topping?: Topping[];
    size?: Array<{ id: number; size: string; price: string | number }>;
}

const props = defineProps<{ product: Product }>();
const emit = defineEmits<{ close: []; addToBasket: [item: unknown] }>();

const quantity = ref(1);
const optionSelections = ref<Record<string, number[]>>({});
const optionQuantities = ref<Record<number, number>>({});
const selectedSize = ref<number | null>(null);
const showAllergyInfo = ref(false);
const groupRefs = ref<Record<string, HTMLDivElement | null>>({});

function convertToppingsToOptionGroups(toppings: Topping[]): OptionGroup[] {
    if (!toppings || toppings.length === 0) return [];
    return toppings
        .filter((topping) => topping && topping.name)
        .map((topping) => {
            const options =
                topping.attachments && topping.attachments.length > 0
                    ? topping.attachments.map((attachment) => ({
                          id: attachment.id,
                          name: stripHtml(attachment.name || ''),
                          price:
                              typeof attachment.price === 'number'
                                  ? attachment.price
                                  : parseFloat(String(attachment.price || 0).replace(/[€\s,]/g, '')) || 0,
                      }))
                    : [{ id: topping.id, name: stripHtml(topping.name), price: 0 }];

            const isRequired = !topping.is_optional;
            return {
                id: `topping-${topping.id}`,
                title: stripHtml(topping.name),
                type: 'multi' as const,
                required: isRequired,
                maxSelect: isRequired ? topping.required : undefined,
                options,
            };
        });
}

const optionGroups = computed(() => {
    const apiToppingGroups =
        props.product.topping && props.product.topping.length > 0
            ? convertToppingsToOptionGroups(props.product.topping)
            : [];
    const allOptionGroups = apiToppingGroups.length > 0 ? apiToppingGroups : props.product.optionGroups || [];
    return [...allOptionGroups].sort((a, b) => {
        if (a.required && !b.required) return -1;
        if (!a.required && b.required) return 1;
        return 0;
    });
});

function getBasePrice() {
    if (!props.product.price) return 0;
    if (typeof props.product.price === 'number') return props.product.price;
    const priceStr = String(props.product.price).replace(/€/g, '').replace(/\s/g, '').trim();
    const parsed = Number.parseFloat(priceStr);
    return isNaN(parsed) ? 0 : parsed;
}

function getSizePrice() {
    if (selectedSize.value && props.product.size) {
        const selectedSizeOption = props.product.size.find((s) => s.id === selectedSize.value);
        if (selectedSizeOption) {
            return typeof selectedSizeOption.price === 'number'
                ? selectedSizeOption.price
                : parseFloat(String(selectedSizeOption.price || 0));
        }
    }
    return 0;
}

function getGroupTotalQuantity(
    groupId: string,
    selections?: Record<string, number[]>,
    quantities?: Record<number, number>,
): number {
    const currentSelections = selections || optionSelections.value;
    const currentQuantities = quantities || optionQuantities.value;
    const selected = currentSelections[groupId] || [];
    let totalQty = 0;
    selected.forEach((optId) => {
        totalQty += currentQuantities[optId] || 1;
    });
    return totalQty;
}

const optionsTotal = computed(() => {
    let total = 0;
    optionGroups.value.forEach((group) => {
        const selected = optionSelections.value[group.id] || [];
        selected.forEach((optId) => {
            const opt = group.options.find((o) => o.id === optId);
            if (opt) {
                const qty = optionQuantities.value[optId] || 1;
                total += opt.price * qty;
            }
        });
    });
    return total;
});

const itemTotal = computed(() => (getBasePrice() + getSizePrice() + optionsTotal.value) * quantity.value);

const canAdd = computed(() => {
    if (props.product.size && props.product.size.length > 0 && !selectedSize.value) return false;
    for (const group of optionGroups.value) {
        if (group.required) {
            const totalQty = getGroupTotalQuantity(group.id);
            const requiredAmount = group.maxSelect || 1;
            if (totalQty < requiredAmount) return false;
        }
    }
    return true;
});

function isGroupComplete(
    group: OptionGroup,
    selections?: Record<string, number[]>,
    quantities?: Record<number, number>,
): boolean {
    if (group.required) {
        const totalQty = getGroupTotalQuantity(group.id, selections, quantities);
        const requiredAmount = group.maxSelect || 1;
        return totalQty >= requiredAmount;
    }
    return true;
}

function scrollToNextRequired(
    currentGroupId: string,
    currentSelections?: Record<string, number[]>,
    currentQuantities?: Record<number, number>,
) {
    const currentIndex = optionGroups.value.findIndex((g) => g.id === currentGroupId);
    if (currentIndex === -1) return;
    for (let i = currentIndex + 1; i < optionGroups.value.length; i++) {
        const group = optionGroups.value[i];
        if (group.required && !isGroupComplete(group, currentSelections, currentQuantities)) {
            const el = groupRefs.value[group.id];
            if (el) {
                setTimeout(() => {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                }, 100);
            }
            break;
        }
    }
}

function setGroupRef(id: string, el: Element | null) {
    groupRefs.value[id] = el instanceof HTMLDivElement ? el : null;
}

function handleRadioSelect(groupId: string, optionId: number) {
    const newSelections = { ...optionSelections.value, [groupId]: [optionId] };
    optionSelections.value = newSelections;
    const group = optionGroups.value.find((g) => g.id === groupId);
    if (group?.required) {
        setTimeout(() => scrollToNextRequired(groupId, newSelections), 200);
    }
}

function handleMultiSelect(groupId: string, optionId: number) {
    const current = optionSelections.value[groupId] || [];
    if (current.includes(optionId)) return;

    const group = optionGroups.value.find((g) => g.id === groupId);
    if (group?.required && group.maxSelect) {
        const currentTotalQty = getGroupTotalQuantity(groupId);
        if (currentTotalQty >= group.maxSelect) return;
    }

    optionQuantities.value = { ...optionQuantities.value, [optionId]: 1 };
    const newSelections = { ...optionSelections.value, [groupId]: [...current, optionId] };
    optionSelections.value = newSelections;

    if (group?.required) {
        const newQuantities = { ...optionQuantities.value, [optionId]: 1 };
        const newTotalQty = getGroupTotalQuantity(groupId, newSelections, newQuantities);
        const isComplete = group.maxSelect ? newTotalQty >= group.maxSelect : newTotalQty > 0;
        if (isComplete) {
            setTimeout(() => scrollToNextRequired(groupId, newSelections, newQuantities), 200);
        }
    }
}

function handleOptionQtyChange(optionId: number, delta: number, groupId?: string) {
    const current = optionQuantities.value[optionId] || 1;
    const newQty = Math.max(0, current + delta);

    if (groupId && delta > 0 && newQty > current) {
        const group = optionGroups.value.find((g) => g.id === groupId);
        if (group?.required && group.maxSelect) {
            const currentTotalQty = getGroupTotalQuantity(groupId);
            const additionalQty = newQty - current;
            if (currentTotalQty + additionalQty > group.maxSelect) return;
        }
    }

    if (newQty === 0) {
        const newPrev = { ...optionQuantities.value };
        delete newPrev[optionId];
        optionQuantities.value = newPrev;
        const newSel: Record<string, number[]> = {};
        Object.entries(optionSelections.value).forEach(([gid, opts]) => {
            newSel[gid] = opts.filter((id) => id !== optionId);
        });
        optionSelections.value = newSel;
        return;
    }

    optionQuantities.value = { ...optionQuantities.value, [optionId]: newQty };

    if (groupId && newQty > 0) {
        setTimeout(() => {
            const group = optionGroups.value.find((g) => g.id === groupId);
            if (group?.required) {
                const newQuantities = { ...optionQuantities.value, [optionId]: newQty };
                const newTotalQty = getGroupTotalQuantity(groupId, optionSelections.value, newQuantities);
                const isComplete = group.maxSelect ? newTotalQty >= group.maxSelect : newTotalQty > 0;
                if (isComplete) {
                    scrollToNextRequired(groupId, optionSelections.value, newQuantities);
                }
            }
        }, 200);
    }
}

function handleAddToBasket() {
    const selectedOptions: {
        groupTitle: string;
        items: { name: string; qty: number; price: number; id: number }[];
    }[] = [];
    optionGroups.value.forEach((group) => {
        const selected = optionSelections.value[group.id] || [];
        if (selected.length > 0) {
            const items = selected.map((optId) => {
                const opt = group.options.find((o) => o.id === optId)!;
                return { name: opt.name, qty: optionQuantities.value[optId] || 1, price: opt.price, id: opt.id };
            });
            selectedOptions.push({ groupTitle: group.title, items });
        }
    });

    const selectedSizeInfo =
        selectedSize.value && props.product.size
            ? props.product.size.find((s) => s.id === selectedSize.value)
            : null;

    emit('addToBasket', {
        ...props.product,
        name: props.product.title || props.product.name || 'Product',
        image: props.product.image ?? null,
        quantity: quantity.value,
        selectedSize: selectedSizeInfo,
        selectedOptions,
        totalPrice: itemTotal.value,
    });
    emit('close');
}

function displayPrice(): string {
    if (props.product.size && props.product.size.length > 0) {
        if (selectedSize.value) {
            const selectedSizeOption = props.product.size.find((s) => s.id === selectedSize.value);
            if (selectedSizeOption) {
                const sizePrice =
                    typeof selectedSizeOption.price === 'number'
                        ? selectedSizeOption.price
                        : parseFloat(String(selectedSizeOption.price || 0));
                return `€ ${sizePrice.toFixed(2)}`;
            }
        } else {
            const prices = props.product.size
                .map((s) => (typeof s.price === 'number' ? s.price : parseFloat(String(s.price || 0))))
                .filter((p) => p > 0);
            if (prices.length > 0) {
                return `from € ${Math.min(...prices).toFixed(2)}`;
            }
        }
    }

    if (!props.product.price || props.product.price === null || props.product.price === 'null') {
        return 'Price on selection';
    }
    if (typeof props.product.price === 'number') {
        return `€ ${props.product.price.toFixed(2)}`;
    }
    const priceStr = String(props.product.price);
    if (priceStr === 'null' || priceStr === 'undefined') return 'Price on selection';
    return priceStr.startsWith('€') ? priceStr : `€ ${priceStr}`;
}

const productTitle = computed(() => props.product.title || props.product.name || 'Product');
</script>

<template>
    <div
        v-if="showAllergyInfo"
        class="fixed inset-0 bg-black/70 dark:bg-black/80 backdrop-blur-sm flex items-end md:items-center justify-center z-[60] p-0 md:p-4"
        @click="showAllergyInfo = false"
    >
        <div
            class="bg-card rounded-t-3xl md:rounded-3xl max-w-md w-full h-[85vh] md:h-auto md:max-h-[80vh] flex flex-col shadow-2xl border-t md:border border-border"
            @click.stop
        >
            <div class="flex items-center gap-2 p-4 md:p-5 pb-3 border-b border-border flex-shrink-0">
                <button
                    type="button"
                    class="w-9 h-9 rounded-full border border-border hover:bg-accent flex items-center justify-center transition"
                    aria-label="Back"
                    @click="showAllergyInfo = false"
                >
                    <ChevronLeft class="w-5 h-5" />
                </button>
                <h2 class="text-lg md:text-xl font-bold text-foreground">Allergy info</h2>
            </div>
            <div class="px-5 py-5 overflow-y-auto flex-1 space-y-3">
                <h3 class="text-xl font-bold text-foreground">{{ productTitle }}</h3>
                <p class="text-muted-foreground text-sm leading-relaxed">
                    If you have a food allergy or intolerance (or someone you're ordering for has), phone the
                    restaurant on
                    <a href="tel:+35361597178" class="text-orange-500 font-semibold hover:underline">061 597 178</a>.
                    Do not order if you cannot get the allergy information you need.
                </p>
            </div>
            <div class="p-4 border-t border-border flex-shrink-0">
                <button
                    type="button"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-2xl transition shadow-lg shadow-orange-500/20"
                    @click="showAllergyInfo = false"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>

    <div
        class="fixed inset-0 bg-black/70 dark:bg-black/80 backdrop-blur-sm flex items-end md:items-center justify-center z-50 p-0 md:p-4"
        @click="emit('close')"
    >
        <div
            class="bg-card rounded-t-3xl md:rounded-3xl max-w-lg w-full h-[94vh] md:h-auto md:max-h-[88vh] flex flex-col shadow-2xl border-t md:border border-border overflow-hidden relative"
            @click.stop
        >
            <div class="flex justify-center pt-2.5 md:hidden absolute top-0 inset-x-0 z-20">
                <div class="w-10 h-1 rounded-full bg-white/50" />
            </div>

            <button
                type="button"
                class="absolute top-3 right-3 w-9 h-9 bg-black/45 hover:bg-black/60 text-white rounded-full flex items-center justify-center transition z-20 backdrop-blur-sm"
                aria-label="Close"
                @click="emit('close')"
            >
                <X class="w-4 h-4" />
            </button>

            <div class="w-full h-48 sm:h-56 md:h-64 bg-muted flex-shrink-0 relative overflow-hidden">
                <StorefrontImage
                    :src="product.image"
                    :alt="productTitle"
                    eager
                    img-class="w-full h-full object-cover"
                />
                <div class="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-card via-card/80 to-transparent" />
            </div>

            <div class="px-4 md:px-5 flex-shrink-0 -mt-10 relative z-10 pb-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-xl md:text-2xl font-bold text-foreground tracking-tight leading-tight">
                            {{ productTitle }}
                        </h2>
                        <p class="mt-1.5 text-orange-500 font-bold text-lg tabular-nums">{{ displayPrice() }}</p>
                    </div>
                    <button
                        type="button"
                        class="w-9 h-9 rounded-full border border-border bg-card/95 hover:bg-accent flex items-center justify-center transition shrink-0 shadow-sm"
                        aria-label="Item information"
                        @click="showAllergyInfo = true"
                    >
                        <Info class="w-4 h-4 text-muted-foreground" />
                    </button>
                </div>
                <p
                    v-if="product.description"
                    class="mt-2 text-muted-foreground text-sm leading-relaxed line-clamp-3"
                >
                    {{ stripHtml(product.description) }}
                </p>
            </div>

            <div class="px-4 md:px-5 py-3 space-y-4 overflow-y-auto flex-1 min-h-0">
                <div v-if="product.size && product.size.length > 0" class="space-y-2.5">
                    <div class="flex justify-between items-center">
                        <h3 class="text-sm font-bold text-foreground">Size</h3>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-500"
                            >Required</span
                        >
                    </div>
                    <div class="grid gap-2">
                        <button
                            v-for="sizeOption in product.size"
                            :key="sizeOption.id"
                            type="button"
                            class="w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl border transition text-left"
                            :class="
                                selectedSize === sizeOption.id
                                    ? 'border-orange-500 bg-orange-500/10 shadow-sm shadow-orange-500/10'
                                    : 'border-border bg-card hover:border-orange-500/30 hover:bg-accent/50'
                            "
                            @click="selectedSize = sizeOption.id"
                        >
                            <div
                                class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                :class="selectedSize === sizeOption.id ? 'border-orange-500' : 'border-border'"
                            >
                                <div
                                    v-if="selectedSize === sizeOption.id"
                                    class="w-2.5 h-2.5 rounded-full bg-orange-500"
                                />
                            </div>
                            <span class="flex-1 text-sm font-medium text-foreground">{{
                                stripHtml(sizeOption.size)
                            }}</span>
                            <span
                                v-if="
                                    (typeof sizeOption.price === 'number'
                                        ? sizeOption.price
                                        : parseFloat(String(sizeOption.price || 0))) > 0
                                "
                                class="text-sm font-semibold text-muted-foreground"
                            >
                                €{{
                                    (
                                        typeof sizeOption.price === 'number'
                                            ? sizeOption.price
                                            : parseFloat(String(sizeOption.price || 0))
                                    ).toFixed(2)
                                }}
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    v-for="group in optionGroups"
                    :key="group.id"
                    :ref="(el) => setGroupRef(group.id, el as Element | null)"
                    class="space-y-2.5"
                >
                    <div class="flex justify-between items-center gap-2">
                        <h3 class="text-sm font-bold text-foreground">{{ group.title }}</h3>
                        <span
                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full shrink-0"
                            :class="
                                group.required
                                    ? 'bg-orange-500/15 text-orange-500'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <template v-if="group.required">
                                {{ group.maxSelect ? `${group.maxSelect} required` : 'Required' }}
                            </template>
                            <template v-else>Optional</template>
                        </span>
                    </div>

                    <div class="grid gap-2">
                        <template v-for="opt in group.options" :key="opt.id">
                            <button
                                v-if="group.type === 'radio'"
                                type="button"
                                class="w-full flex items-center gap-3 px-3.5 py-3 rounded-2xl border transition text-left"
                                :class="
                                    (optionSelections[group.id] || []).includes(opt.id)
                                        ? 'border-orange-500 bg-orange-500/10'
                                        : 'border-border bg-card hover:border-orange-500/30 hover:bg-accent/50'
                                "
                                @click="handleRadioSelect(group.id, opt.id)"
                            >
                                <div
                                    class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                    :class="
                                        (optionSelections[group.id] || []).includes(opt.id)
                                            ? 'border-orange-500'
                                            : 'border-border'
                                    "
                                >
                                    <div
                                        v-if="(optionSelections[group.id] || []).includes(opt.id)"
                                        class="w-2.5 h-2.5 rounded-full bg-orange-500"
                                    />
                                </div>
                                <span class="flex-1 text-sm font-medium text-foreground">{{ opt.name }}</span>
                                <span v-if="opt.price > 0" class="text-sm text-muted-foreground"
                                    >+€{{ opt.price.toFixed(2) }}</span
                                >
                            </button>

                            <div
                                v-else
                                class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl border border-border bg-card"
                            >
                                <template
                                    v-if="
                                        !group.required || (optionSelections[group.id] || []).includes(opt.id)
                                    "
                                >
                                    <div class="flex items-center gap-1 bg-muted/70 rounded-full p-0.5 border border-border">
                                        <button
                                            type="button"
                                            :disabled="
                                                !(optionSelections[group.id] || []).includes(opt.id) ||
                                                !(optionQuantities[opt.id] || 0)
                                            "
                                            class="w-7 h-7 rounded-full flex items-center justify-center transition disabled:opacity-40"
                                            @click="
                                                !(optionSelections[group.id] || []).includes(opt.id) &&
                                                !(optionQuantities[opt.id] || 0)
                                                    ? handleMultiSelect(group.id, opt.id)
                                                    : handleOptionQtyChange(opt.id, -1, group.id)
                                            "
                                        >
                                            <Minus class="w-3.5 h-3.5" />
                                        </button>
                                        <span
                                            class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs"
                                            :class="
                                                (optionSelections[group.id] || []).includes(opt.id) &&
                                                (optionQuantities[opt.id] || 0) > 0
                                                    ? 'bg-orange-500 text-white'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{ optionQuantities[opt.id] || 0 }}
                                        </span>
                                        <button
                                            type="button"
                                            :disabled="
                                                (optionSelections[group.id] || []).includes(opt.id) &&
                                                !!group.required &&
                                                !!group.maxSelect &&
                                                getGroupTotalQuantity(group.id) >= group.maxSelect
                                            "
                                            class="w-7 h-7 rounded-full flex items-center justify-center transition disabled:opacity-40"
                                            @click="
                                                !(optionSelections[group.id] || []).includes(opt.id)
                                                    ? handleMultiSelect(group.id, opt.id)
                                                    : handleOptionQtyChange(opt.id, 1, group.id)
                                            "
                                        >
                                            <Plus class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="
                                        !!group.maxSelect && getGroupTotalQuantity(group.id) >= group.maxSelect
                                    "
                                    class="w-8 h-8 rounded-full border border-border flex items-center justify-center transition hover:border-orange-500 hover:text-orange-500 disabled:opacity-40"
                                    @click="
                                        (!group.maxSelect || getGroupTotalQuantity(group.id) < group.maxSelect) &&
                                            handleMultiSelect(group.id, opt.id)
                                    "
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                </button>
                                <span class="flex-1 text-sm font-medium text-foreground">{{ opt.name }}</span>
                                <span v-if="opt.price > 0" class="text-sm text-muted-foreground"
                                    >+€{{ opt.price.toFixed(2) }}</span
                                >
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div
                class="p-3.5 md:p-4 bg-card/95 backdrop-blur border-t border-border flex-shrink-0 sticky bottom-0 z-10"
            >
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center bg-muted rounded-2xl border border-border">
                        <button
                            type="button"
                            class="px-3 py-2.5 text-muted-foreground hover:text-foreground transition"
                            aria-label="Decrease quantity"
                            @click="quantity = Math.max(1, quantity - 1)"
                        >
                            <Minus class="w-4 h-4" />
                        </button>
                        <span class="text-foreground font-bold text-sm w-7 text-center">{{ quantity }}</span>
                        <button
                            type="button"
                            class="px-3 py-2.5 text-muted-foreground hover:text-foreground transition"
                            aria-label="Increase quantity"
                            @click="quantity++"
                        >
                            <Plus class="w-4 h-4" />
                        </button>
                    </div>

                    <button
                        type="button"
                        :disabled="!canAdd"
                        class="flex-1 flex items-center justify-between font-bold py-3 px-4 rounded-2xl transition text-sm shadow-lg"
                        :class="
                            canAdd
                                ? 'bg-orange-500 hover:bg-orange-600 text-white shadow-orange-500/25'
                                : 'bg-muted text-muted-foreground cursor-not-allowed shadow-none'
                        "
                        @click="handleAddToBasket"
                    >
                        <span>Add to basket</span>
                        <span>€{{ itemTotal.toFixed(2) }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
