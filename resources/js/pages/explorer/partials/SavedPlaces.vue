<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bookmark } from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import BusinessResults from '@/pages/explorer/partials/BusinessResults.vue';
import { index } from '@/routes/consumer/saved-places';
import type {
    SavedPlaceChange,
    SavedPlacesPage,
    SearchBusiness,
} from '@/types';
import { latestRequest } from '@/utils/requests';

const emit = defineEmits<{ select: [business: SearchBusiness] }>();
const page = usePage();
const places = ref<SavedPlacesPage | null>(null);
const loading = ref(false);
const error = ref('');
const heading = ref<HTMLElement>();
const requests = latestRequest();
let requestedPage = 1;

async function load(target = 1, restoreFocus = false) {
    const request = requests.start();
    requestedPage = target;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(index.url({ query: { page: target } }), {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Saved places unavailable');
        }

        const result = (await response.json()) as SavedPlacesPage;

        if (request.isCurrent()) {
            places.value = result;
            requestedPage = result.meta.current_page;

            if (restoreFocus) {
                void nextTick(() =>
                    heading.value?.focus({ preventScroll: true }),
                );
            }
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load your saved places. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

function handleSelect(id: number) {
    const business = places.value?.data.find((place) => place.id === id);

    if (business) {
        emit('select', business);
    }
}

function handleChange(event: Event) {
    const change = (event as CustomEvent<SavedPlaceChange>).detail;

    if (change.userId === page.props.auth?.user?.id) {
        void load(requestedPage, !change.saved);
    }
}

onMounted(() => {
    void load();
    window.addEventListener('saved-place-changed', handleChange);
});

onUnmounted(() => {
    requests.cancel();
    window.removeEventListener('saved-place-changed', handleChange);
});
</script>

<template>
    <section class="py-5" aria-label="Your saved places" :aria-busy="loading">
        <header class="px-5 pb-4">
            <h2 ref="heading" tabindex="-1" class="text-[13px] font-semibold">
                Saved places
                <span
                    v-if="places"
                    class="ml-1 text-[10px] font-normal text-slate-400"
                    >{{ places.meta.total }}</span
                >
            </h2>
            <p class="mt-2 text-[11px] leading-5 text-slate-500">
                Only you can see this list. Choose a place to open it on the
                map. Unavailable businesses are not shown.
            </p>
        </header>
        <div
            v-if="error"
            role="alert"
            class="mx-5 mb-4 rounded-xl bg-red-50 p-3 text-xs text-red-600"
        >
            {{ error }}
            <button
                type="button"
                class="ml-1 h-9 underline"
                @click="load(requestedPage)"
            >
                Retry
            </button>
        </div>
        <p
            v-if="loading && !places"
            role="status"
            class="px-5 py-8 text-center text-xs text-slate-500"
        >
            Loading saved places...
        </p>
        <BusinessResults
            v-else-if="places?.data.length"
            :places="places.data"
            :pagination="places.meta"
            :loading="loading"
            @select="handleSelect"
            @page-change="load($event)"
        />
        <div
            v-else-if="!loading && !error"
            class="mx-5 rounded-xl border border-dashed border-explorer-line px-5 py-10 text-center"
        >
            <Bookmark :size="28" class="mx-auto mb-4 text-explorer-blue" />
            <h3 class="text-[13px] font-semibold">Keep a place for later</h3>
            <p class="mt-2 text-[11px] leading-6 text-slate-500">
                Tap the bookmark on a business to save it here.
            </p>
        </div>
    </section>
</template>
