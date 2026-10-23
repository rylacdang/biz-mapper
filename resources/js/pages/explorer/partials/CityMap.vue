<script setup lang="ts">
import 'maplibre-gl/dist/maplibre-gl.css';
import { Compass, LocateFixed, Minus, Plus, Layers } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useCityMap } from '@/composables/useCityMap';
import { useExplorerLocation } from '@/composables/useExplorerLocation';
import UserLocationMarker from '@/pages/explorer/partials/UserLocationMarker.vue';
import type {
    BusinessMarker,
    Category,
    Coordinates,
    SearchBusiness,
    SearchState,
} from '@/types';
import type { WalkingRoute } from '@/types/routing';
import { formatDistance } from '@/utils/geography';
import { readSearch } from '@/utils/search';

const props = defineProps<{
    places: BusinessMarker[];
    categories: Category[];
    center: Coordinates;
    selected: SearchBusiness | null;
    route: WalkingRoute | null;
    radius: SearchState | null;
    moved: boolean;
    panelOpen: boolean;
    collectionFocus?: Coordinates[] | null;
    collectionView?: boolean;
}>();

const emit = defineEmits<{
    select: [id: number];
    move: [center: Coordinates];
    search: [];
    locate: [center: Coordinates];
    userLocation: [center: Coordinates];
}>();

const container = ref<HTMLElement>();

const location = useExplorerLocation();
const { error: locationError, locating, accuracy } = location;

let alive = true;
let mapInitialized = false;

const city = useCityMap(
    (id) => emit('select', id),
    (center) => emit('move', center),
);
const { ready, error, is3d, userMarkerElement } = city;

onMounted(() => {
    if (props.collectionView) {
        initializeMap(props.center, true);

        return;
    }

    // Saved search coordinates describe the search area, not the user's location.
    if (readSearch(window.location.href)) {
        initializeMap(props.center, true);
        handleLocate(false);
    } else {
        handleLocate();
    }
});

function initializeMap(center: Coordinates, closeUp = false) {
    if (!container.value || mapInitialized || !alive) {
        return;
    }

    mapInitialized = true;
    city.init(
        container.value,
        center,
        props.categories,
        () => {
            synchronizePlaces();
            city.setRadius(props.radius);
            city.highlight(props.selected?.id ?? null);

            if (props.selected && !props.route) {
                city.focus(props.selected, props.panelOpen);
            }

            city.setRoute(props.route, props.panelOpen);

            if (
                props.collectionFocus?.length &&
                !props.selected &&
                !props.route
            ) {
                city.fitPlaces(
                    props.collectionFocus,
                    props.panelOpen,
                    locationError.value && window.innerWidth < 768 ? 90 : 0,
                );
            }
        },
        closeUp ? 18.5 : 15,
        closeUp ? 40 : undefined,
    );
}

// Keep the canvas synchronized with Vue state without rebuilding the map instance.
function synchronizePlaces() {
    const places = [...props.places];

    if (
        props.selected &&
        !places.some((place) => place.id === props.selected?.id)
    ) {
        places.push({
            id: props.selected.id,
            slug: props.selected.slug,
            lat: props.selected.lat,
            lng: props.selected.lng,
            category_id: props.selected.category?.id ?? null,
        });
    }

    city.setPlaces(places);
}

watch([() => props.places, () => props.selected], synchronizePlaces);

watch(() => props.radius, city.setRadius);

watch(
    () => props.route,
    (route) => city.setRoute(route, props.panelOpen),
);

watch(() => props.center, city.moveCenter);

watch(
    () => props.collectionFocus,
    (places) => {
        if (places?.length) {
            city.fitPlaces(
                places,
                props.panelOpen,
                locationError.value && window.innerWidth < 768 ? 90 : 0,
            );
        }
    },
);

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
        if (props.route) {
            city.setRoute(props.route, visible);
        } else if (props.selected) {
            city.focus(props.selected, visible);
        }
    },
);

function handleLocate(recenter = true) {
    // Reuse one location request for first load and the My location control.
    if (locating.value) {
        return;
    }

    location.locate(
        (position, first) => {
            initializeMap(position, true);
            city.setUserLocation(position);
            emit('userLocation', position);

            // Refine the marker without moving the camera or repeating the search.
            if (recenter && first && !props.route) {
                city.focus(
                    position,
                    props.panelOpen,
                    18.5,
                    is3d.value ? 40 : 0,
                );
                emit('locate', position);
            }
        },
        () => {
            if (!mapInitialized) {
                locationError.value += ' Showing the default area.';
                initializeMap(props.center);
            }
        },
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

    <!-- Distinguish the discovery area from the device's location uncertainty -->
    <div
        v-if="radius && ready"
        class="absolute top-20 right-4 z-2 flex flex-col gap-1.5 rounded-xl bg-white/95 px-3 py-2 text-[11px] text-slate-600 shadow-sm max-md:right-16 max-md:text-[10px]"
        aria-label="Map circle legend"
    >
        <span class="flex items-center gap-2">
            <span
                class="size-2.5 rounded-full border-2 border-blue-600 bg-blue-100"
                aria-hidden="true"
            ></span>
            {{ formatDistance(radius.radius) }} search area
        </span>

        <span v-if="userMarkerElement" class="flex items-center gap-2">
            <span
                class="size-2.5 rounded-full border border-dashed border-amber-600 bg-amber-100"
                aria-hidden="true"
            ></span>
            GPS accuracy · {{ Math.round(accuracy) }} m
        </span>
    </div>

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
            @click="handleLocate()"
        >
            <LocateFixed :size="21" />
        </button>
    </nav>
</template>
