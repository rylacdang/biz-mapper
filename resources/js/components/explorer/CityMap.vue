<script setup lang="ts">
import 'maplibre-gl/dist/maplibre-gl.css';
import { Compass, LocateFixed, Minus, Plus, Layers } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import UserLocationMarker from '@/components/explorer/UserLocationMarker.vue';
import { useCityMap } from '@/composables/useCityMap';
import { readSearch } from '@/lib/explorer';
import type {
    Category,
    Coordinates,
    SearchBusiness,
    SearchState,
} from '@/types';

const props = defineProps<{
    places: SearchBusiness[];
    categories: Category[];
    center: Coordinates;
    selected: SearchBusiness | null;
    radius: SearchState | null;
    moved: boolean;
    panelOpen: boolean;
}>();

const emit = defineEmits<{
    select: [id: number];
    move: [center: Coordinates];
    search: [];
    locate: [center: Coordinates];
}>();

const container = ref<HTMLElement>();

const locationError = ref('');
const locating = ref(false);
const accuracy = ref(0);

let alive = true;
let mapInitialized = false;

const city = useCityMap(
    (id) => emit('select', id),
    (center) => emit('move', center),
);
const { ready, error, is3d, userMarkerElement } = city;

onMounted(() => {
    // Resolve fresh-visit location before creating the map, avoiding a Makati flash.
    // Explicit search links retain their area for reload and history restoration.
    if (readSearch(window.location.href)) {
        initializeMap(props.center);
    } else {
        handleLocate();
    }
});

function initializeMap(center: Coordinates, atUserLocation = false) {
    if (!container.value || mapInitialized || !alive) {
        return;
    }

    mapInitialized = true;
    city.init(
        container.value,
        center,
        props.categories,
        () => {
            city.setPlaces(props.places);
            city.setRadius(props.radius);
        },
        atUserLocation ? 18.5 : 15,
        atUserLocation ? 40 : undefined,
    );
}

// Keep the canvas synchronized with Vue state without rebuilding the map instance.
watch(() => props.places, city.setPlaces);

watch(() => props.radius, city.setRadius);

watch(() => props.center, city.moveCenter);

watch(
    () => props.selected,
    (place) => {
        city.highlight(place?.id ?? null);

        if (place) {
            city.focus(place, props.panelOpen);
        }
    },
);

watch(
    () => props.panelOpen,
    (visible) => {
        if (props.selected) {
            city.focus(props.selected, visible);
        }
    },
);

function handleLocate() {
    // Reuse one location request for first load and the My location control.
    if (locating.value) {
        return;
    }

    locationError.value = '';

    if (!navigator.geolocation) {
        locationError.value =
            'This browser cannot provide your location. Showing the default area.';
        initializeMap(props.center);

        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            if (!alive) {
                return;
            }

            const center = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };
            initializeMap(center, true);
            accuracy.value = position.coords.accuracy;
            city.setUserLocation({ ...center, accuracy: accuracy.value });
            city.focus(center, props.panelOpen, 18.5, is3d.value ? 40 : 0);
            emit('locate', center);
            locating.value = false;
        },
        (failure) => {
            if (alive) {
                const messages: Record<number, string> = {
                    1: 'Location permission is blocked. Allow location for this site, then tap My location.',
                    2: 'Your device could not determine its location. Check location services, then tap My location.',
                    3: 'Finding your location took too long. Tap My location to retry.',
                };
                locationError.value =
                    messages[failure.code] ??
                    'Location is unavailable. Tap My location to retry.';

                if (!mapInitialized) {
                    locationError.value += ' Showing the default area.';
                    initializeMap(props.center);
                }

                locating.value = false;
            }
        },
        { enableHighAccuracy: true, maximumAge: 60000, timeout: 20000 },
    );
}

onUnmounted(() => {
    alive = false;
});
</script>

<template>
    <!-- Vue owns the marker content; MapLibre owns its geographic position -->
    <Teleport v-if="userMarkerElement" :to="userMarkerElement">
        <UserLocationMarker :accuracy="accuracy" />
    </Teleport>

    <!-- MapLibre canvas -->
    <div
        ref="container"
        :class="[
            'absolute! inset-0',
            'max-md:group-data-[panel-open=true]/explorer:[&_.maplibregl-ctrl-bottom-right]:bottom-[43dvh]!',
            'max-md:group-data-[panel-open=true]/explorer:group-data-[sheet=collapsed]/explorer:[&_.maplibregl-ctrl-bottom-right]:bottom-[100px]!',
            'max-md:group-data-[panel-open=true]/explorer:group-data-[sheet=expanded]/explorer:[&_.maplibregl-ctrl-bottom-right]:bottom-[calc(100dvh-88px)]!',
        ]"
        aria-label="Interactive business map"
    ></div>

    <!-- Map loading and recoverable failures -->
    <div
        v-if="!ready && !error"
        :class="[
            'absolute top-[45%] left-1/2 z-2 max-w-[350px] -translate-x-1/2 rounded-[12px] bg-white',
            'px-[22px] py-4 text-[12px] shadow-[0_4px_20px_#172b4d14] max-md:top-[33%]',
            'max-md:left-[48%] max-md:w-55 max-md:text-[11px]',
        ]"
    >
        <span
            class="mr-[10px] inline-block h-2 w-2 rounded-[50%] bg-explorer-blue motion-safe:animate-pulse"
        ></span>
        {{ locating ? 'Finding your location…' : 'Opening your neighborhood…' }}
    </div>

    <div
        v-if="error || locationError"
        :class="[
            'absolute top-auto bottom-[85px] left-1/2 z-2 max-w-[350px] -translate-x-1/2',
            'rounded-[12px] bg-[#fff9eb] px-[22px] py-4 text-[12px] text-[#8b642c]',
            'shadow-[0_4px_20px_#172b4d14] max-md:top-[255px] max-md:bottom-auto max-md:left-[45%]',
            'max-md:w-[250px] max-md:p-[10px]',
        ]"
        role="status"
    >
        {{ locationError || error }}
    </div>

    <!-- Explicit search after moving the map -->
    <button
        v-if="moved && ready"
        :class="[
            'absolute top-[88px] left-1/2 z-3 flex -translate-x-1/2 gap-[22px] rounded-[25px] border',
            'border-white bg-white px-5 py-3 text-[12px] font-[750] text-explorer-blue',
            'shadow-[0_4px_18px_#172b4d15] max-md:top-[88px] max-md:left-[45%] max-md:px-[17px]',
            'max-md:py-[10px] max-md:text-[11px]',
        ]"
        @click="emit('search')"
    >
        Search this area
        <span>↗</span>
    </button>

    <!-- Camera and location controls -->
    <nav
        class="absolute right-4 bottom-16 z-3 flex flex-col gap-3 max-md:top-[88px] max-md:right-[13px] max-md:bottom-auto max-md:gap-[7px]"
        aria-label="Map controls"
    >
        <button
            :aria-pressed="is3d"
            title="Switch between 2D and 3D"
            :class="[
                'flex h-[54px] w-11 flex-col items-center justify-center gap-[3px] rounded-[12px] border-0',
                'bg-white text-[10px] font-[800] text-explorer-blue shadow-[0_4px_18px_#172b4d15]',
                'max-md:h-12 max-md:w-[38px]',
            ]"
            @click="city.toggle3d"
        >
            <Layers :size="17" />
            {{ is3d ? '3D' : '2D' }}
        </button>

        <div
            class="rounded-[12px] border border-white bg-white shadow-[0_4px_18px_#172b4d15]"
        >
            <button
                :class="[
                    'flex h-11 w-11 items-center justify-center rounded-[12px] border-0 bg-white',
                    'text-[#526880] first:rounded-[12px_12px_0_0] first:border-b first:border-[#edf1f6]',
                    'last:rounded-[0_0_12px_12px] max-md:h-[38px] max-md:w-[38px]',
                ]"
                aria-label="Zoom in"
                @click="city.zoom(1)"
            >
                <Plus :size="19" />
            </button>

            <button
                :class="[
                    'flex h-11 w-11 items-center justify-center rounded-[12px] border-0 bg-white',
                    'text-[#526880] first:rounded-[12px_12px_0_0] first:border-b first:border-[#edf1f6]',
                    'last:rounded-[0_0_12px_12px] max-md:h-[38px] max-md:w-[38px]',
                ]"
                aria-label="Zoom out"
                @click="city.zoom(-1)"
            >
                <Minus :size="19" />
            </button>
        </div>

        <button
            class="flex h-11 w-11 items-center justify-center rounded-[12px] border-0 bg-white text-[#526880] shadow-[0_4px_18px_#172b4d15] max-md:h-[38px] max-md:w-[38px]"
            aria-label="Reset north"
            title="Reset north"
            @click="city.north"
        >
            <Compass :size="21" />
        </button>

        <button
            class="flex h-11 w-11 items-center justify-center rounded-[12px] border-0 bg-white text-[#526880] shadow-[0_4px_18px_#172b4d15] max-md:h-[38px] max-md:w-[38px]"
            :disabled="locating"
            aria-label="My location"
            title="My location"
            @click="handleLocate"
        >
            <LocateFixed :size="21" />
        </button>
    </nav>
</template>
