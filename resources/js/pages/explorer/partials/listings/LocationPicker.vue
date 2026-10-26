<script setup lang="ts">
import type { Map, Marker } from 'maplibre-gl';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { MAP_STYLE } from '@/constants/map';
import { maplibregl } from '@/maps/maplibre';
import type { Coordinates } from '@/types';

const props = defineProps<{ lat: number; lng: number }>();

const emit = defineEmits<{ change: [coords: Coordinates] }>();

const container = ref<HTMLElement>();

const error = ref('');

let map: Map | undefined;
let marker: Marker | undefined;
let resize: ResizeObserver | undefined;

onMounted(() => {
    if (!container.value) {
        return;
    }

    try {
        map = new maplibregl.Map({
            container: container.value,
            style: MAP_STYLE,
            center: [props.lng, props.lat],
            zoom: 15,
            pitch: 0,
            dragRotate: false,
            touchPitch: false,
            attributionControl: { compact: true },
        });
        marker = new maplibregl.Marker({ color: '#2563eb', draggable: true })
            .setLngLat([props.lng, props.lat])
            .addTo(map);
        marker.on('dragend', () => {
            const p = marker!.getLngLat();
            emit('change', { lat: p.lat, lng: p.lng });
        });
        map.on('click', (e) => {
            marker?.setLngLat(e.lngLat);
            emit('change', { lat: e.lngLat.lat, lng: e.lngLat.lng });
        });
        map.on('error', () => {
            error.value =
                'Some map tiles could not load. You can still move the pin.';
        });
        map.addControl(
            new maplibregl.NavigationControl({ showCompass: false }),
            'top-right',
        );
        resize = new ResizeObserver(() => map?.resize());
        resize.observe(container.value);
    } catch {
        error.value =
            'Map unavailable. Choose an address suggestion to set the pin, or reload to try the map again.';
    }
});

// Address suggestions and manual pin choices share the same coordinates.
watch(
    () => [props.lat, props.lng],
    () => {
        if (Number.isFinite(props.lat) && Number.isFinite(props.lng)) {
            marker?.setLngLat([props.lng, props.lat]);
            map?.jumpTo({ center: [props.lng, props.lat] });
        }
    },
);

onUnmounted(() => {
    resize?.disconnect();
    marker?.remove();
    map?.remove();
});
</script>

<template>
    <!-- The pin sets coordinates while the owner keeps their written address. -->
    <div>
        <div
            ref="container"
            class="h-60 w-full overflow-hidden rounded-[12px] bg-[#edf3fa]"
            aria-label="Choose business location"
        ></div>

        <p
            v-if="error"
            class="rounded-[8px] bg-[#fff6e6] p-[10px] text-[11px] leading-[1.6] text-[#997432]"
            role="status"
        >
            {{ error }}
        </p>
    </div>
</template>
