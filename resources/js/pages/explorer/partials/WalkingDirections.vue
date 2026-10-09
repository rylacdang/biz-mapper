<script setup lang="ts">
import { Footprints, X } from 'lucide-vue-next';
import { computed } from 'vue';
import type { WalkingRoute } from '@/types/routing';
import { formatDistance } from '@/utils/geography';

const props = defineProps<{
    route: WalkingRoute | null;
    loading: boolean;
    error: string;
}>();
const emit = defineEmits<{ clear: []; retry: [] }>();
const minutes = computed(() =>
    Math.max(1, Math.ceil((props.route?.duration ?? 0) / 60)),
);
</script>

<template>
    <section
        v-if="route || loading || error"
        class="mt-4 space-y-3 rounded-xl border border-blue-100 bg-blue-50/70 p-3"
        aria-label="Walking directions"
        aria-live="polite"
    >
        <div class="flex items-center justify-between gap-3">
            <span
                class="flex items-center gap-2 text-xs font-semibold text-explorer-blue"
            >
                <Footprints :size="15" />
                Walking route
            </span>
            <button
                type="button"
                class="flex h-9 items-center gap-1 rounded-lg px-2 text-xs text-slate-500 hover:bg-blue-100"
                @click="emit('clear')"
            >
                <X :size="14" /> Clear route
            </button>
        </div>
        <p v-if="loading" class="text-xs text-slate-500">
            Finding a walking route…
        </p>
        <template v-else-if="route">
            <p class="text-xs font-semibold text-explorer-ink">
                {{ formatDistance(route.distance / 1000) }} · About
                {{ minutes }} min on foot
            </p>
            <p class="text-[11px] leading-relaxed text-slate-500">
                From your location along mapped streets and footpaths.
            </p>
        </template>
        <div v-else-if="error" class="space-y-2">
            <p role="alert" class="text-xs text-red-600">{{ error }}</p>
            <button
                type="button"
                class="h-9 rounded-lg bg-explorer-blue px-3 text-xs font-semibold text-white hover:bg-blue-700"
                @click="emit('retry')"
            >
                Retry directions
            </button>
        </div>
        <p class="text-[10px] text-slate-500">
            Routing by
            <a
                href="https://routing.openstreetmap.de/about.html"
                target="_blank"
                rel="noopener noreferrer"
                class="underline"
                >FOSSGIS / OSRM</a
            >
            ·
            <a
                href="https://www.openstreetmap.org/copyright"
                target="_blank"
                rel="noopener noreferrer"
                class="underline"
                >© OpenStreetMap contributors</a
            >
            ·
            <a
                href="https://www.openstreetmap.org/fixthemap"
                target="_blank"
                rel="noopener noreferrer"
                class="underline"
                >Fix the map</a
            >
        </p>
    </section>
</template>
