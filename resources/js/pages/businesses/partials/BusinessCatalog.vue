<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
import BusinessOfferingCard from '@/components/businesses/BusinessOfferingCard.vue';
import { index } from '@/routes/businesses/offerings';
import type { OfferingPage } from '@/types';
import { latestRequest } from '@/utils/requests';

const props = defineProps<{ initial: OfferingPage; slug: string }>();
const catalog = ref(props.initial);
const loading = ref(false);
const error = ref('');
const requests = latestRequest();
let requestedPage = 1;

watch([() => props.initial, () => props.slug], () => {
    requests.cancel();
    catalog.value = props.initial;
    loading.value = false;
    error.value = '';
});

async function load(page: number) {
    const request = requests.start();
    requestedPage = page;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(
            index.url(props.slug, { query: { page } }),
            {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('Catalog unavailable');
        }

        const result = (await response.json()) as OfferingPage;

        if (request.isCurrent()) {
            catalog.value = result;
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'We could not load these items. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

onUnmounted(() => requests.cancel());

function retry() {
    return load(requestedPage);
}
</script>

<template>
    <section
        v-if="catalog.meta.total > 0"
        class="mt-8 border-b border-explorer-line pb-8"
        aria-labelledby="business-catalog-title"
        :aria-busy="loading"
    >
        <div class="mb-5 flex items-center justify-between gap-4">
            <div>
                <h2
                    id="business-catalog-title"
                    class="font-explorer-heading text-lg font-semibold"
                >
                    Products & services
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    What this business offers.
                </p>
            </div>
            <span class="text-[11px] text-slate-400"
                >{{ catalog.meta.total }}
                {{ catalog.meta.total === 1 ? 'item' : 'items' }}</span
            >
        </div>
        <p
            v-if="error"
            role="alert"
            class="mb-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800"
        >
            {{ error }}
            <button
                type="button"
                class="ml-2 font-semibold underline"
                @click="retry"
            >
                Try again
            </button>
        </p>
        <p v-if="loading" role="status" class="mb-3 text-xs text-slate-500">
            Loading catalog...
        </p>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <BusinessOfferingCard
                v-for="item in catalog.data"
                :key="item.id"
                :item="item"
            />
        </div>
        <nav
            v-if="catalog.meta.last_page > 1"
            class="mt-5 flex items-center justify-between gap-4"
            aria-label="Catalog pages"
        >
            <button
                type="button"
                :disabled="loading || catalog.meta.current_page <= 1"
                class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold hover:bg-blue-50 disabled:opacity-40"
                @click="load(catalog.meta.current_page - 1)"
            >
                <ChevronLeft :size="15" /> Previous
            </button>
            <span class="text-[11px] text-slate-500" aria-live="polite"
                >{{ catalog.meta.current_page }} /
                {{ catalog.meta.last_page }}</span
            >
            <button
                type="button"
                :disabled="
                    loading ||
                    catalog.meta.current_page >= catalog.meta.last_page
                "
                class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold hover:bg-blue-50 disabled:opacity-40"
                @click="load(catalog.meta.current_page + 1)"
            >
                Next <ChevronRight :size="15" />
            </button>
        </nav>
    </section>
</template>
