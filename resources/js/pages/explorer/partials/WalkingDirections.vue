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
        class="overflow-hidden rounded-xl border border-blue-100 bg-white"
        aria-label="Walking directions"
        aria-live="polite"
    >
        <div class="flex items-center justify-between gap-3 px-3 pt-3">
            <span
                class="flex items-center gap-2 text-[11px] font-semibold text-explorer-ink"
            >
                <span
                    class="grid size-8 place-items-center rounded-lg bg-blue-50 text-explorer-blue"
                >
                    <Footprints :size="15" />
                </span>
                Walking route
            </span>
            <button
                type="button"
                aria-label="Clear route"
                title="Clear route"
                class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-explorer-cloud hover:text-explorer-ink"
                @click="emit('clear')"
            >
                <X :size="15" />
            </button>
        </div>
        <p v-if="loading" class="px-3 py-3 text-[11px] text-slate-500">
            Finding a walking route…
        </p>
        <template v-else-if="route">
            <dl
                class="mx-3 mt-3 grid grid-cols-2 divide-x divide-blue-100 rounded-lg bg-blue-50/70 py-3"
            >
                <div class="px-3">
                    <dt class="text-[10px] text-slate-500">On foot</dt>
                    <dd class="mt-1 text-sm font-semibold text-explorer-ink">
                        About {{ minutes }} min
                    </dd>
                </div>
                <div class="px-3">
                    <dt class="text-[10px] text-slate-500">Distance</dt>
                    <dd class="mt-1 text-sm font-semibold text-explorer-ink">
                        {{ formatDistance(route.distance / 1000) }}
                    </dd>
                </div>
            </dl>
            <p class="px-3 py-3 text-[10px] leading-relaxed text-slate-500">
                From your location along mapped streets and footpaths.
            </p>
        </template>
        <div v-else-if="error" class="space-y-2 px-3 py-3">
            <p role="alert" class="text-xs text-red-600">{{ error }}</p>
            <button
                type="button"
                class="h-9 rounded-lg bg-explorer-blue px-3 text-xs font-semibold text-white hover:bg-blue-700"
                @click="emit('retry')"
            >
                Retry directions
            </button>
        </div>
        <div
            class="space-y-1 border-t border-explorer-line px-3 py-2 text-[9px] leading-relaxed text-slate-400"
        >
            <p>
                Routing by
                <a
                    href="https://routing.openstreetmap.de/about.html"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:text-explorer-blue hover:underline"
                    >FOSSGIS / OSRM</a
                >
            </p>
            <p>
                <a
                    href="https://www.openstreetmap.org/copyright"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:text-explorer-blue hover:underline"
                    >© OpenStreetMap contributors</a
                >
                ·
                <a
                    href="https://www.openstreetmap.org/fixthemap"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:text-explorer-blue hover:underline"
                    >Fix the map</a
                >
            </p>
        </div>
    </section>
</template>
