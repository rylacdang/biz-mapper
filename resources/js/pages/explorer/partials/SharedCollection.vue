<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FolderHeart, Globe2, MapPin } from 'lucide-vue-next';
import BusinessResults from '@/components/businesses/BusinessResults.vue';
import type { PublicCollection, PublicCollectionPlacesPage } from '@/types';

defineProps<{
    collection: PublicCollection;
    places: PublicCollectionPlacesPage;
    loading: boolean;
    error: string;
}>();
const emit = defineEmits<{
    select: [id: number];
    pageChange: [page: number];
    retry: [];
    showMap: [];
}>();
</script>

<template>
    <section aria-label="Shared collection" class="pt-5">
        <header class="px-5 pb-5">
            <span
                class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-2 py-1 text-[10px] font-semibold text-explorer-blue"
                ><Globe2 :size="12" /> Public collection</span
            >
            <h2
                tabindex="-1"
                class="mt-3 font-explorer-heading text-base font-semibold break-words"
            >
                {{ collection.name }}
            </h2>
            <p
                v-if="collection.description"
                class="mt-3 text-xs leading-6 whitespace-pre-line text-slate-500"
            >
                {{ collection.description }}
            </p>
            <p class="mt-2 text-[11px] leading-6 text-slate-500">
                {{ places.meta.total }} available
                {{ places.meta.total === 1 ? 'place' : 'places' }}. The map
                shows the current page. Select a place to start exploring.
            </p>
            <div class="mt-3 flex gap-2">
                <button
                    type="button"
                    :disabled="!places.data.length || loading"
                    class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl bg-explorer-blue text-[11px] font-semibold text-white disabled:opacity-50"
                    @click="emit('showMap')"
                >
                    <MapPin :size="14" /> Fit places
                </button>
                <Link
                    :href="collection.url"
                    class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl border border-explorer-line text-[11px] font-semibold text-explorer-blue hover:bg-blue-50"
                    >Collection page <ArrowUpRight :size="14"
                /></Link>
            </div>
        </header>
        <p
            v-if="error"
            role="alert"
            class="mx-5 mb-4 rounded-xl bg-red-50 p-3 text-[11px] text-red-600"
        >
            {{ error }}
            <button type="button" class="h-9 underline" @click="emit('retry')">
                Retry
            </button>
        </p>
        <BusinessResults
            v-if="places.data.length"
            :places="places.data"
            :pagination="places.meta"
            :loading="loading"
            @select="emit('select', $event)"
            @page-change="emit('pageChange', $event)"
        />
        <div
            v-else
            class="mx-5 mb-5 rounded-xl border border-dashed border-explorer-line px-5 py-10 text-center"
        >
            <FolderHeart :size="28" class="mx-auto mb-3 text-explorer-blue" />
            <h3 class="text-[13px] font-semibold">
                No available places right now
            </h3>
            <p class="mt-2 text-[11px] leading-6 text-slate-500">
                There are no available businesses to show in this collection.
            </p>
        </div>
    </section>
</template>
