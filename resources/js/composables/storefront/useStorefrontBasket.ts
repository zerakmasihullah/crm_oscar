import { computed, readonly, ref } from 'vue';

export type StorefrontBasketItem = {
    id: number;
    name: string;
    price: string;
    quantity: number;
    totalPrice: number;
    image?: string | null;
    [key: string]: unknown;
};

const STORAGE_KEY = 'basketItems';
const items = ref<StorefrontBasketItem[]>([]);
let hydrated = false;
let listenersBound = false;

function readStorage(): StorefrontBasketItem[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function persistNow() {
    try {
        if (items.value.length > 0) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items.value));
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // ignore quota / private mode
    }
}

function hydrate() {
    if (hydrated || typeof window === 'undefined') return;
    hydrated = true;
    items.value = readStorage();

    if (!listenersBound) {
        listenersBound = true;
        window.addEventListener('basketCleared', () => {
            items.value = [];
            persistNow();
        });
        // Cross-tab sync only (same-tab writes are already in memory)
        window.addEventListener('storage', (e: StorageEvent) => {
            if (e.key === STORAGE_KEY) {
                items.value = readStorage();
            }
        });
    }
}

export function useStorefrontBasket() {
    hydrate();

    const count = computed(() =>
        items.value.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0),
    );
    const subtotal = computed(() =>
        items.value.reduce((sum, item) => sum + (Number(item.totalPrice) || 0), 0),
    );

    function addItem(item: StorefrontBasketItem) {
        items.value = [...items.value, item];
        persistNow();
    }

    function updateQuantity(index: number, newQty: number) {
        if (newQty < 1) {
            removeItem(index);
            return;
        }
        const next = [...items.value];
        const current = next[index];
        if (!current) return;
        const unit = Number(current.totalPrice) / Math.max(1, Number(current.quantity) || 1);
        next[index] = {
            ...current,
            quantity: newQty,
            totalPrice: unit * newQty,
        };
        items.value = next;
        persistNow();
    }

    function removeItem(index: number) {
        items.value = items.value.filter((_, i) => i !== index);
        persistNow();
    }

    function clear() {
        items.value = [];
        persistNow();
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('basketCleared'));
        }
    }

    function reload() {
        items.value = readStorage();
    }

    return {
        items: readonly(items),
        itemsRef: items,
        count,
        subtotal,
        addItem,
        updateQuantity,
        removeItem,
        clear,
        reload,
    };
}
