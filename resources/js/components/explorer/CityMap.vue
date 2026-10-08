<script setup lang="ts">
import 'maplibre-gl/dist/maplibre-gl.css';
import { Compass, LocateFixed, Minus, Plus, Layers } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useCityMap } from '@/composables/useCityMap';
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

let alive = true;

const city = useCityMap(
    (id) => emit('select', id),
    (center) => emit('move', center),
);
const { ready, error, is3d } = city;

onMounted(() => {
    if (container.value) {
        city.init(container.value, props.center, props.categories, () => {
            city.setPlaces(props.places);
            city.setRadius(props.radius);
        });
    }
});

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
    // Request permission only from this action; denial leaves normal discovery usable.
    locationError.value = '';

    if (!navigator.geolocation) {
        locationError.value = 'Location isn’t available in this browser.';

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
            city.focus(center, props.panelOpen);
            emit('locate', center);
            locating.value = false;
        },
        () => {
            if (alive) {
                locationError.value =
                    'Location wasn’t available. You can move the map and search anywhere.';
                locating.value = false;
            }
        },
        { timeout: 8000 },
    );
}

onUnmounted(() => {
    alive = false;
});
</script>

<template>
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
        Opening your neighborhood…
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
