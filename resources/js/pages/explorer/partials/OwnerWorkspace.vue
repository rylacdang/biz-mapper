<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    Star,
    Store,
    Trash2,
} from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
import { show } from '@/routes/businesses';
import type {
    Business,
    BusinessInsights,
    OwnerListingPagination,
} from '@/types';
import { photoUrl } from '@/utils/businesses';

const props = defineProps<{
    places: Business[];
    insights: Record<number, BusinessInsights>;
    pagination: OwnerListingPagination;
    trash: Business[];
    showingTrash: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{
    create: [];
    edit: [listing: Business];
    toggleTrash: [];
    restore: [id: number];
}>();

const query = ref(props.pagination.query);
const status = ref(props.pagination.status);
const loading = ref(false);
const error = ref('');
const failedLogos = ref<Record<number, string>>({});
let cancelVisit: (() => void) | undefined;

function handleLoadListings(page = 1, refreshOnly = false) {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    const handleFailure = () => {
        error.value = 'Could not load your listings. Please try again.';

        return false;
    };
    const filters = refreshOnly
        ? props.pagination
        : { query: query.value.trim(), status: status.value };

    router.reload({
        data: {
            listings_query: filters.query,
            listings_status: filters.status,
            listings_page: page,
        },
        only: refreshOnly
            ? ['ownerInsights']
            : ['myBusinesses', 'ownerInsights', 'ownerPagination'],
        preserveUrl: true,
        onCancelToken: (token) => {
            cancelVisit = () => token.cancel();
        },
        onError: handleFailure,
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
        onFinish: () => {
            loading.value = false;
            cancelVisit = undefined;
        },
    });
}

watch(
    () => props.pagination,
    (pagination) => {
        query.value = pagination.query;
        status.value = pagination.status;
    },
);

onUnmounted(() => cancelVisit?.());
</script>

<template>
    <section class="px-5 py-5 max-md:pt-4" :aria-busy="loading">
        <header class="flex items-center justify-between gap-3">
            <div>
                <h2
                    class="text-lg font-semibold tracking-tight text-explorer-ink"
                >
                    {{ showingTrash ? 'Deleted listings' : 'My listings' }}
                </h2>
                <p class="mt-1 text-[10px] text-slate-400">
                    {{ showingTrash ? trash.length : pagination.total }}
                    {{
                        (showingTrash ? trash.length : pagination.total) === 1
                            ? 'business'
                            : 'businesses'
                    }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-explorer-cloud hover:text-explorer-blue focus-visible:outline-2 focus-visible:outline-explorer-blue"
                    :aria-label="
                        showingTrash ? 'Back to listings' : 'Open trash'
                    "
                    @click="emit('toggleTrash')"
                >
                    <RotateCcw v-if="showingTrash" :size="15" />
                    <Trash2 v-else :size="15" />
                </button>
                <button
                    v-if="!showingTrash"
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-explorer-blue px-3 text-[11px] font-semibold text-white hover:bg-blue-700"
                    @click="emit('create')"
                >
                    <Plus :size="14" aria-hidden="true" /> Add business
                </button>
            </div>
        </header>

        <!-- Listing filters remain separate from the public map search. -->
        <form
            v-if="!showingTrash"
            class="mt-4 space-y-2"
            @submit.prevent="handleLoadListings()"
        >
            <div
                class="flex h-9 items-center rounded-lg border border-explorer-line bg-explorer-cloud focus-within:border-explorer-blue"
            >
                <input
                    v-model="query"
                    type="search"
                    maxlength="100"
                    aria-label="Search your listings"
                    placeholder="Search your listings"
                    class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-[11px] text-explorer-ink outline-none placeholder:text-slate-400"
                    :disabled="loading"
                />
                <button
                    type="submit"
                    aria-label="Search listings"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-explorer-blue hover:bg-blue-50 disabled:cursor-default disabled:opacity-50"
                    :disabled="loading"
                >
                    <Search :size="15" />
                </button>
            </div>
            <div class="flex items-center justify-between gap-3">
                <select
                    v-model="status"
                    aria-label="Listing status"
                    class="h-9 min-w-0 rounded-lg border border-explorer-line bg-white px-2.5 text-[11px] text-slate-600 outline-none focus:border-explorer-blue"
                    :disabled="loading"
                    @change="handleLoadListings()"
                >
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                </select>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2 text-[11px] text-explorer-blue hover:bg-blue-50 disabled:cursor-default disabled:opacity-50"
                    :disabled="loading"
                    @click="handleLoadListings(pagination.current_page, true)"
                >
                    <RefreshCw
                        :size="12"
                        :class="{ 'motion-safe:animate-spin': loading }"
                        aria-hidden="true"
                    />
                    Refresh
                </button>
            </div>
        </form>

        <p
            v-if="error"
            role="status"
            class="mt-3 text-[11px] leading-5 text-red-600"
        >
            {{ error }}
        </p>
        <p v-if="loading" role="status" class="sr-only">Loading listings</p>

        <div class="mt-2">
            <article
                v-for="place in showingTrash ? trash : places"
                :key="place.id"
                class="flex items-start gap-3 border-b border-explorer-line py-4"
            >
                <div
                    class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-explorer-line bg-explorer-cloud"
                >
                    <img
                        v-if="
                            photoUrl(place.logo) &&
                            failedLogos[place.id] !== place.logo
                        "
                        :src="photoUrl(place.logo)"
                        :alt="place.name + ' logo'"
                        class="size-full bg-white object-contain"
                        @error="failedLogos[place.id] = place.logo ?? ''"
                    />
                    <Store
                        v-else
                        :size="21"
                        class="text-slate-400"
                        aria-hidden="true"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex min-w-0 items-center gap-2">
                        <h3
                            class="min-w-0 flex-1 truncate text-xs font-semibold text-explorer-ink"
                        >
                            <span v-if="showingTrash">{{ place.name }}</span>
                            <Link
                                v-else
                                :href="show.url(place.slug)"
                                class="hover:text-explorer-blue focus-visible:outline-2 focus-visible:outline-explorer-blue"
                                >{{ place.name }}</Link
                            >
                        </h3>
                        <span
                            class="shrink-0 rounded px-1.5 py-0.5 text-[8px] font-medium"
                            :class="
                                !showingTrash && place.status === 'active'
                                    ? 'bg-blue-50 text-explorer-blue'
                                    : 'bg-slate-100 text-slate-500'
                            "
                        >
                            {{
                                showingTrash
                                    ? 'Deleted'
                                    : place.status === 'active'
                                      ? 'Active'
                                      : 'Pending'
                            }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-[10px] text-slate-400">
                        {{ place.category?.name }}
                    </p>
                    <p
                        v-if="!showingTrash && insights[place.id]"
                        class="mt-2 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[10px] text-slate-500"
                    >
                        <span
                            title="Profile visits counted once per account or guest session within 24 hours. Your own visits are excluded."
                            >{{
                                insights[
                                    place.id
                                ].profile_views.toLocaleString()
                            }}
                            views</span
                        >
                        <span aria-hidden="true" class="text-slate-300">·</span>
                        <span
                            >{{
                                insights[place.id].review_count.toLocaleString()
                            }}
                            {{
                                insights[place.id].review_count === 1
                                    ? 'review'
                                    : 'reviews'
                            }}</span
                        >
                        <span aria-hidden="true" class="text-slate-300">·</span>
                        <span class="inline-flex items-center gap-1"
                            ><Star
                                :size="10"
                                class="text-explorer-blue"
                                aria-hidden="true"
                            />{{
                                insights[place.id].average_rating?.toFixed(1) ??
                                'No ratings'
                            }}</span
                        >
                    </p>
                </div>
                <button
                    type="button"
                    class="-mt-1 -mr-1 grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-explorer-cloud hover:text-explorer-blue focus-visible:outline-2 focus-visible:outline-explorer-blue disabled:cursor-default disabled:opacity-50"
                    :disabled="busy || loading"
                    :aria-label="
                        (showingTrash ? 'Restore ' : 'Edit ') + place.name
                    "
                    @click="
                        showingTrash
                            ? emit('restore', place.id)
                            : emit('edit', place)
                    "
                >
                    <RotateCcw v-if="showingTrash" :size="16" />
                    <Pencil v-else :size="16" />
                </button>
            </article>
        </div>

        <nav
            v-if="!showingTrash && pagination.total > 0"
            aria-label="Listing pages"
            class="mt-3 flex items-center justify-between gap-3"
        >
            <span class="text-[10px] text-slate-400"
                >{{ pagination.from }}–{{ pagination.to }} of
                {{ pagination.total }}</span
            >
            <div v-if="pagination.last_page > 1" class="flex gap-1">
                <button
                    type="button"
                    aria-label="Previous listings page"
                    class="grid size-9 place-items-center rounded-lg border border-explorer-line text-slate-500 hover:bg-explorer-cloud disabled:cursor-default disabled:opacity-40"
                    :disabled="loading || pagination.current_page <= 1"
                    @click="handleLoadListings(pagination.current_page - 1)"
                >
                    <ChevronLeft :size="15" />
                </button>
                <button
                    type="button"
                    aria-label="Next listings page"
                    class="grid size-9 place-items-center rounded-lg border border-explorer-line text-slate-500 hover:bg-explorer-cloud disabled:cursor-default disabled:opacity-40"
                    :disabled="
                        loading ||
                        pagination.current_page >= pagination.last_page
                    "
                    @click="handleLoadListings(pagination.current_page + 1)"
                >
                    <ChevronRight :size="15" />
                </button>
            </div>
        </nav>

        <div
            v-if="!(showingTrash ? trash : places).length"
            class="py-8 text-center"
        >
            <Store
                :size="28"
                class="mx-auto text-slate-300"
                aria-hidden="true"
            />
            <h3 class="mt-3 text-xs font-medium text-explorer-ink">
                {{
                    showingTrash
                        ? 'Trash is empty'
                        : pagination.query || pagination.status !== 'all'
                          ? 'No listings found'
                          : 'Put your business on the map'
                }}
            </h3>
            <p class="mt-2 text-[11px] leading-5 text-slate-400">
                {{
                    showingTrash
                        ? 'Deleted listings will appear here.'
                        : pagination.query || pagination.status !== 'all'
                          ? 'Try another business name or status.'
                          : 'Add your business to get started.'
                }}
            </p>
        </div>
    </section>
</template>
