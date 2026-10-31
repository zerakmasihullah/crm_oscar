<script setup lang="ts">
import { MapPin, Navigation } from '@lucide/vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        latitude: string;
        longitude: string;
        height?: string;
        onLocationChange?: (lat: string, lng: string) => void;
    }>(),
    { height: '400px' },
);

const emit = defineEmits<{
    locationChange: [lat: string, lng: string];
}>();

function notifyLocation(lat: string, lng: string) {
    emit('locationChange', lat, lng);
    props.onLocationChange?.(lat, lng);
}

const mapContainer = ref<HTMLDivElement | null>(null);
const currentLat = ref(props.latitude);
const currentLng = ref(props.longitude);
const isDragging = ref(false);
const isGettingLocation = ref(false);
const locationError = ref<string | null>(null);

let map: L.Map | null = null;
let marker: L.Marker | null = null;

const isCompact = computed(() => !!props.height && parseInt(props.height) < 200);

function createCustomIcon() {
    return L.icon({
        iconUrl:
            'data:image/svg+xml;base64,' +
            btoa(`
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="#f97316" stroke="#fff" stroke-width="2.5">
        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
        <circle cx="12" cy="10" r="4" fill="#fff"></circle>
      </svg>
    `),
        iconSize: [48, 48],
        iconAnchor: [24, 48],
        popupAnchor: [0, -48],
    });
}

function hasValidCoords(lat: string, lng: string) {
    return !!(lat && lng && lat !== '0' && lng !== '0');
}

function updateMarker(lat: number, lng: number) {
    if (!map) return;
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { icon: createCustomIcon(), draggable: true }).addTo(map);
        marker.on('dragstart', () => {
            isDragging.value = true;
        });
        marker.on('drag', () => {
            if (!marker) return;
            const pos = marker.getLatLng();
            currentLat.value = pos.lat.toFixed(6);
            currentLng.value = pos.lng.toFixed(6);
        });
        marker.on('dragend', () => {
            if (!marker) return;
            const pos = marker.getLatLng();
            const newLat = pos.lat.toFixed(6);
            const newLng = pos.lng.toFixed(6);
            currentLat.value = newLat;
            currentLng.value = newLng;
            isDragging.value = false;
            notifyLocation(newLat, newLng);
        });
    }
}

function initMap() {
    if (!mapContainer.value || map) return;

    const defaultLat = currentLat.value ? parseFloat(currentLat.value) : 52.668;
    const defaultLng = currentLng.value ? parseFloat(currentLng.value) : -8.6264;
    const zoom = hasValidCoords(currentLat.value, currentLng.value) ? 16 : 13;

    map = L.map(mapContainer.value, { zoomControl: true }).setView([defaultLat, defaultLng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    map.on('click', (e: L.LeafletMouseEvent) => {
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        currentLat.value = lat;
        currentLng.value = lng;
        updateMarker(e.latlng.lat, e.latlng.lng);
        notifyLocation(lat, lng);
    });

    if (hasValidCoords(currentLat.value, currentLng.value)) {
        updateMarker(parseFloat(currentLat.value), parseFloat(currentLng.value));
    }

    setTimeout(() => map?.invalidateSize(), 100);
}

function handleGetCurrentLocation() {
    if (!navigator.geolocation) {
        locationError.value = 'Geolocation is not supported by your browser';
        return;
    }

    isGettingLocation.value = true;
    locationError.value = null;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude.toFixed(6);
            const lng = position.coords.longitude.toFixed(6);
            currentLat.value = lat;
            currentLng.value = lng;
            notifyLocation(lat, lng);
            if (map) {
                map.setView([parseFloat(lat), parseFloat(lng)], 16);
                updateMarker(parseFloat(lat), parseFloat(lng));
            }
            isGettingLocation.value = false;
        },
        (error) => {
            isGettingLocation.value = false;
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    locationError.value = 'Location access denied. Please enable location permissions.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    locationError.value = 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    locationError.value = 'Location request timed out.';
                    break;
                default:
                    locationError.value = 'An unknown error occurred.';
                    break;
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
    );
}

watch(
    () => [props.latitude, props.longitude] as const,
    ([lat, lng]) => {
        if (isDragging.value) return;
        if (hasValidCoords(lat, lng)) {
            currentLat.value = lat;
            currentLng.value = lng;
            if (map) {
                map.setView([parseFloat(lat), parseFloat(lng)], 16);
                updateMarker(parseFloat(lat), parseFloat(lng));
            }
        } else if (!lat || !lng || lat === '0' || lng === '0') {
            currentLat.value = '';
            currentLng.value = '';
        }
    },
);

onMounted(async () => {
    await nextTick();
    initMap();
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
});
</script>

<template>
    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between" :class="isCompact ? 'mb-1.5' : 'mb-3'">
            <div class="flex items-center gap-2">
                <MapPin :class="[isCompact ? 'w-4 h-4' : 'w-5 h-5', 'text-orange-500']" />
                <span class="text-foreground font-medium" :class="isCompact ? 'text-xs' : 'text-sm'"
                    >Select your location</span
                >
            </div>
            <button
                type="button"
                :disabled="isGettingLocation"
                class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed dark:bg-orange-600"
                :class="isCompact ? 'px-2 py-1 text-xs' : 'px-3 py-1.5 text-xs'"
                @click="handleGetCurrentLocation"
            >
                <Navigation :class="[isCompact ? 'w-3 h-3' : 'w-3.5 h-3.5', isGettingLocation ? 'animate-spin' : '']" />
                {{ isGettingLocation ? 'Getting...' : isCompact ? 'Current' : 'Use Current Location' }}
            </button>
        </div>

        <div
            v-if="locationError"
            class="bg-red-500/10 dark:bg-red-500/20 border border-red-500/50 rounded-lg text-red-600 dark:text-red-300"
            :class="isCompact ? 'mb-1.5 p-2 text-xs' : 'mb-3 p-3 text-xs'"
        >
            {{ locationError }}
        </div>

        <div
            class="relative rounded-lg overflow-hidden bg-card shadow-xl"
            :class="isCompact ? 'border border-orange-500/30' : 'border-2 border-orange-500/30'"
            :style="{ height: height, minHeight: height }"
        >
            <div ref="mapContainer" class="rounded-lg" style="height: 100%; width: 100%; z-index: 0" />

            <div
                v-if="!hasValidCoords(currentLat, currentLng)"
                class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-background/90 dark:bg-black/80 backdrop-blur-md rounded-lg px-4 py-2 text-foreground text-sm z-[1000] pointer-events-none border border-border shadow-lg"
            >
                <div class="flex items-center gap-2">
                    <MapPin class="w-4 h-4 text-orange-500" />
                    <span>Click on the map to set your location</span>
                </div>
            </div>
        </div>

        <div
            v-if="hasValidCoords(currentLat, currentLng) && !isCompact"
            class="mt-3 flex items-center gap-2 text-sm text-green-600 dark:text-green-400 bg-green-500/10 border border-green-500/30 rounded-lg px-4 py-2"
        >
            <MapPin class="w-5 h-5 flex-shrink-0 text-green-500" />
            <span class="flex-1"
                >Location selected: {{ parseFloat(currentLat).toFixed(4) }},
                {{ parseFloat(currentLng).toFixed(4) }}</span
            >
        </div>
    </div>
</template>
