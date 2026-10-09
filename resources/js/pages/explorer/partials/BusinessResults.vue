<script setup lang="ts">
import { ChevronLeft, ChevronRight, Store } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { SearchBusiness } from '@/types';
import { photoUrl } from '@/utils/businesses';
import { formatDistance } from '@/utils/geography';

const props = defineProps<{
    places: SearchBusiness[];
    loading: boolean;
    canExpand?: boolean;
}>();

const emit = defineEmits<{
    select: [id: number];
    expand: [];
    pageChange: [];
}>();

const PAGE_SIZE = 20;
const page = ref(1);
const pageCount = computed(() => Math.ceil(props.places.length / PAGE_SIZE));
const start = computed(() => (page.value - 1) * PAGE_SIZE);
const visiblePlaces = computed(() =>
    props.places.slice(start.value, start.value + PAGE_SIZE),
);

// New searches start at the nearest results; map pins retain every match.
watch(
    () => props.places,
    () => {
        page.value = 1;
    },
);

function handlePage(offset: number) {
    page.value = Math.min(pageCount.value, Math.max(1, page.value + offset));
    emit('pageChange');
}
</script>

<template>
    <section
        class="px-5 pb-5"
        aria-label="Nearby business results"
        :aria-busy="loading"
    >
        <!-- Compact rows keep names, categories, and distances easy to scan. -->
        <div class="space-y-3">
            <button
                v-for="place in visiblePlaces"
                :key="place.id"
                type="button"
                class="group/place flex w-full items-center gap-3 rounded-xl border border-explorer-line bg-white p-2.5 text-left transition-colors hover:bg-blue-50/40 motion-reduce:transition-none"
                @click="emit('select', place.id)"
            >
                <span
                    class="relative grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border-2 border-explorer-line bg-explorer-cloud text-slate-300 transition-colors group-hover/place:border-explorer-blue group-focus-visible/place:border-explorer-blue motion-reduce:transition-none"
                >
                    <Store :size="24" aria-hidden="true" />
                    <img
                        v-if="
                            photoUrl(place.logo) || photoUrl(place.cover_photo)
                        "
                        :src="
                            photoUrl(place.logo) || photoUrl(place.cover_photo)
                        "
                        alt=""
                        loading="lazy"
                        class="absolute inset-0 h-full w-full object-cover"
                        @error="
                            ($event.target as HTMLImageElement).hidden = true
                        "
                    />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span
                            class="min-w-0 truncate text-[13px] leading-5 font-semibold text-explorer-ink group-hover/place:text-explorer-blue"
                        >
                            {{ place.name }}
                        </span>
                        <span
                            v-if="place.distance !== undefined"
                            class="shrink-0 text-[10px] font-medium text-explorer-green"
                            :title="`${formatDistance(place.distance)} from search center`"
                        >
                            {{ formatDistance(place.distance) }}
                        </span>
                    </span>
                    <span
                        class="mt-1 flex items-center gap-2 text-[10px] leading-4"
                    >
                        <span
                            class="min-w-0 flex-1 truncate font-medium"
                            :style="{
                                color: place.category?.color || '#2563eb',
                            }"
                            >{{
                                place.category?.name || 'Local business'
                            }}</span
                        >
                    </span>
                    <span
                        class="mt-1 block truncate text-[10px] leading-4 text-slate-500"
                        :title="place.address"
                    >
                        {{ place.address }}
                    </span>
                </span>
            </button>
        </div>

        <nav
            v-if="pageCount > 1 && !loading"
            aria-label="Results pages"
            class="mt-3 space-y-3 border-t border-explorer-line pt-4"
        >
            <p
                class="text-center text-[10px] text-slate-500"
                aria-live="polite"
            >
                {{ start + 1 }} -
                {{ Math.min(start + PAGE_SIZE, places.length) }} of
                {{ places.length }} places
            </p>
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    :disabled="page === 1"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] font-semibold hover:bg-explorer-cloud disabled:opacity-40"
                    @click="handlePage(-1)"
                >
                    <ChevronLeft :size="14" /> Previous
                </button>
                <span class="text-[10px] text-slate-400"
                    >{{ page }} / {{ pageCount }}</span
                >
                <button
                    type="button"
                    :disabled="page === pageCount"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] font-semibold hover:bg-explorer-cloud disabled:opacity-40"
                    @click="handlePage(1)"
                >
                    Next <ChevronRight :size="14" />
                </button>
            </div>
        </nav>

        <p
            v-if="loading"
            role="status"
            class="py-6 text-center text-xs text-slate-500"
        >
            Finding nearby places...
        </p>

        <div
            v-if="!places.length && !loading"
            class="py-8 text-center text-slate-500"
        >
            <Store class="mx-auto mb-3 text-slate-300" :size="30" />
            <h3 class="text-[13px] font-semibold text-explorer-ink">
                No places here yet
            </h3>
            <p class="mt-2 text-xs leading-relaxed">
                Try a wider radius or a different category.
            </p>
            <button
                v-if="canExpand"
                type="button"
                class="mt-4 h-9 rounded-xl bg-explorer-blue px-4 text-xs font-semibold text-white hover:bg-blue-700"
                @click="emit('expand')"
            >
                Expand to 1 km
            </button>
        </div>
    </section>
</template>
