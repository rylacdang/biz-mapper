<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Store, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useExplorerPanel } from '@/composables/useExplorerPanel';
import { useExplorerSearch } from '@/composables/useExplorerSearch';
import { useOwnerWorkspace } from '@/composables/useOwnerWorkspace';
import { DEFAULT_CENTER } from '@/constants/map';
import BusinessDetails from '@/pages/explorer/partials/BusinessDetails.vue';
import BusinessResults from '@/pages/explorer/partials/BusinessResults.vue';
import CityMap from '@/pages/explorer/partials/CityMap.vue';
import ExplorerSearch from '@/pages/explorer/partials/ExplorerSearch.vue';
import ExplorerToolbar from '@/pages/explorer/partials/ExplorerToolbar.vue';
import ListingDrawer from '@/pages/explorer/partials/listings/ListingDrawer.vue';
import OwnerWorkspace from '@/pages/explorer/partials/OwnerWorkspace.vue';
import type { Business, Category, Coordinates, SearchBusiness } from '@/types';
import { searchParams } from '@/utils/search';

// Page data and discovery state
const props = withDefaults(
    defineProps<{
        categories?: Category[];
        allBusinesses?: SearchBusiness[];
        myBusinesses?: Business[];
        trashBusinesses?: Business[];
        canRegister?: boolean;
    }>(),
    {
        categories: () => [],
        allBusinesses: () => [],
        myBusinesses: () => [],
        trashBusinesses: () => [],
        canRegister: false,
    },
);

const page = usePage();
const owner = computed(() => page.props.auth?.user?.role === 'owner');

const {
    workspace,
    showingTrash,
    busy,
    trashError,
    drawerOpen,
    editing,
    handleOpenDrawer,
    handleCloseDrawer,
    handleToggleTrash,
    handleRestoreListing,
    handleShowExplore,
    handleShowListings: showOwnerListings,
} = useOwnerWorkspace(owner);

const {
    selected,
    panelScroll,
    panelOpen,
    sheet,
    handleOpenPanel,
    handleClosePanel,
    handleSheetDragEnd,
    handleSheetDragStart,
    handleExpandSheet,
    handleCollapseSheet,
} = useExplorerPanel(workspace);

const center = ref<Coordinates>(DEFAULT_CENTER);
const userLocation = ref<Coordinates | null>(null);
const searchState = useExplorerSearch(
    () => props.allBusinesses,
    () => center.value,
);

const {
    keyword,
    category,
    radius,
    listings,
    loading,
    error,
    lastSearch,
    searchArea,
    visibleSearchArea,
    moved,
} = searchState;

center.value = searchState.initialCenter;
const mapCenter = ref<Coordinates>(searchState.initialCenter);

// Discovery and panel actions
function handleSelectBusiness(id: number) {
    const place = listings.value.find((p) => p.id === id);

    if (place) {
        selected.value = {
            ...props.allBusinesses.find((p) => p.id === id),
            ...place,
        };
        sheet.value = 'half';
        panelOpen.value = true;
    }
}

function handleSearchNearby() {
    if (!panelOpen.value) {
        handleOpenPanel();
    }

    workspace.value = false;
    selected.value = null;
    panelOpen.value = true;
    sheet.value = 'half';
    searchState.search();
}

function handleMapMove(next: Coordinates) {
    center.value = next;
    const anchor = lastSearch.value ?? searchState.initialCenter;
    moved.value =
        Math.abs(next.lat - anchor.lat) + Math.abs(next.lng - anchor.lng) >
        0.003;
}

function handleSearchMapArea() {
    selected.value = null;
    searchState.search();
}

function handleLocate(location: Coordinates) {
    center.value = location;
    panelOpen.value = true;
    workspace.value = false;
    selected.value = null;
    sheet.value = 'half';
    searchState.search({
        ...location,
        keyword: keyword.value,
        category: category.value,
        radius: radius.value,
    });
}

function handleBackToResults() {
    selected.value = null;
}

function handleShowListings() {
    showOwnerListings();
    selected.value = null;
}

function handleResetSearch() {
    searchState.reset();
}

function handleRetrySearch() {
    searchState.search(searchArea.value ?? undefined);
}

function handleExpandSearch() {
    radius.value = 1;

    if (searchArea.value) {
        searchState.search({ ...searchArea.value, radius: 1 });
    }
}

// Reactive synchronization and navigation lifecycle
watch(lastSearch, (state) => {
    // Clearing results leaves the camera at its current position.
    if (
        state &&
        (state.lat !== mapCenter.value.lat || state.lng !== mapCenter.value.lng)
    ) {
        mapCenter.value = { lat: state.lat, lng: state.lng };
    }
});

watch(listings, (places) => {
    if (selected.value && !places.some((p) => p.id === selected.value!.id)) {
        selected.value = null;
    }
});

// Restore filters on Inertia history changes without adding another history entry.
const removeNavigation = router.on('navigate', (event) => {
    const url = event.detail.page.url;
    const query = lastSearch.value ? searchParams(lastSearch.value) : '';
    const expectedUrl = `${window.location.pathname}?${query}`;

    if (url !== expectedUrl) {
        searchState.restore(url);
    }
});

onMounted(() => {
    searchState.restore(window.location.href);
});

onUnmounted(removeNavigation);
</script>

<template>
    <Head title="Discover your neighborhood" />

    <main
        :class="[
            'group/explorer relative h-[100dvh] w-full overflow-hidden bg-explorer-cloud',
            'font-explorer-body text-[14px] text-explorer-ink scheme-light',
            '[&_:focus-visible]:outline-3 [&_:focus-visible]:outline-offset-3',
            '[&_:focus-visible]:outline-explorer-blue [&_a]:[-webkit-tap-highlight-color:transparent]',
            '[&_button]:cursor-pointer [&_button]:[-webkit-tap-highlight-color:transparent]',
            '[&_button:disabled]:cursor-wait [&_button:disabled]:opacity-55',
            '[&_h1]:font-explorer-heading [&_h2]:font-explorer-heading [&_h3]:font-explorer-heading',
        ]"
        :data-sheet="sheet"
        :data-panel-open="panelOpen"
    >
        <!-- Full-screen city map -->
        <CityMap
            :panel-open="panelOpen"
            :places="listings"
            :categories="categories"
            :center="mapCenter"
            :selected="selected"
            :radius="visibleSearchArea"
            :moved="moved && !workspace"
            @select="handleSelectBusiness"
            @move="handleMapMove"
            @search="handleSearchMapArea"
            @locate="handleLocate"
            @user-location="userLocation = $event"
        />

        <!-- Floating search, account, and owner controls -->
        <ExplorerToolbar
            v-model:keyword="keyword"
            :count="listings.length"
            :loading="loading"
            :owner="owner"
            :panel-open="panelOpen"
            :can-register="canRegister"
            @search="handleSearchNearby"
            @explore="handleOpenPanel()"
            @listings="handleOpenPanel('listings')"
            @create="handleOpenDrawer()"
        />

        <!-- On-demand explorer panel and mobile sheet -->
        <aside
            v-if="panelOpen"
            @keydown.esc="handleClosePanel"
            :class="[
                'absolute top-[88px] bottom-6 left-4 z-4 flex w-[340px] flex-col overflow-hidden',
                'rounded-[20px] border border-white bg-white shadow-[0_12px_50px_#172b4d19]',
                'max-md:top-auto max-md:bottom-0 max-md:left-0 max-md:h-[43dvh] max-md:w-full',
                'max-md:rounded-[24px_24px_0_0] max-md:transition-[height] max-md:duration-200',
                'max-md:group-data-[sheet=collapsed]/explorer:h-[100px]',
                'max-md:group-data-[sheet=expanded]/explorer:h-[calc(100dvh-88px)]',
                'max-md:motion-reduce:transition-none',
            ]"
            aria-label="Business explorer"
        >
            <div
                class="relative hidden h-7 min-h-7 touch-none items-center justify-center max-md:flex"
                @pointerdown="handleSheetDragStart"
                @pointerup="handleSheetDragEnd"
            >
                <span class="h-1 w-9 rounded bg-[#dce4ef]"></span>

                <div class="absolute right-3 flex gap-[3px]">
                    <button
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-[#f3f6fb] text-[#768ba5] max-md:h-[23px] max-md:w-[25px] max-md:bg-white"
                        aria-label="Expand results"
                        @click="handleExpandSheet"
                    >
                        <ChevronUp :size="16" />
                    </button>

                    <button
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-[#f3f6fb] text-[#768ba5] max-md:h-[23px] max-md:w-[25px] max-md:bg-white"
                        aria-label="Collapse results"
                        @click="handleCollapseSheet"
                    >
                        <ChevronDown :size="16" />
                    </button>
                </div>
            </div>

            <div
                class="flex shrink-0 items-center justify-between border-b border-explorer-line px-5 py-3.5 max-md:py-2"
            >
                <div>
                    <h1 class="text-sm font-semibold">
                        {{
                            workspace
                                ? 'Your business tools'
                                : selected
                                  ? 'Place details'
                                  : 'Explore nearby'
                        }}
                    </h1>

                    <p class="mt-0.5 text-[10px] text-slate-400">
                        {{
                            workspace
                                ? 'Manage your local presence'
                                : 'Find something worth stopping for'
                        }}
                    </p>
                </div>

                <button
                    aria-label="Close explorer panel"
                    class="grid size-8 place-items-center rounded-xl bg-slate-50 text-slate-500 hover:bg-slate-100"
                    @click="handleClosePanel"
                >
                    <X :size="17" />
                </button>
            </div>

            <!-- Owner workspace tabs -->
            <nav
                v-if="owner"
                class="mx-5 mt-4 mb-0 flex gap-1 rounded-[10px] bg-[#f2f5fa] p-1 max-md:mt-[3px] max-md:shrink-0 max-md:group-data-[sheet=collapsed]/explorer:hidden"
                aria-label="Explorer workspace"
            >
                <button
                    :class="[
                        'flex flex-1 items-center justify-center gap-[6px] rounded-[7px] border-0 bg-transparent',
                        'p-[9px] text-[11px] font-[650] text-[#7b8ba0] data-[active=true]:bg-white',
                        'data-[active=true]:text-explorer-blue data-[active=true]:shadow-[0_2px_5px_#172b4d0a]',
                    ]"
                    :data-active="!workspace"
                    @click="handleShowExplore"
                >
                    Explore
                </button>

                <button
                    :class="[
                        'flex flex-1 items-center justify-center gap-[6px] rounded-[7px] border-0 bg-transparent',
                        'p-[9px] text-[11px] font-[650] text-[#7b8ba0] data-[active=true]:bg-white',
                        'data-[active=true]:text-explorer-blue data-[active=true]:shadow-[0_2px_5px_#172b4d0a]',
                    ]"
                    :data-active="workspace"
                    @click="handleShowListings"
                >
                    <Store :size="15" />
                    My listings
                </button>
            </nav>

            <!-- Category and radius filters -->
            <div
                v-if="!workspace && !selected"
                class="pt-4 max-md:group-data-[sheet=collapsed]/explorer:hidden"
            >
                <ExplorerSearch
                    :show-keyword="false"
                    v-model:keyword="keyword"
                    v-model:category="category"
                    v-model:radius="radius"
                    :categories="categories"
                    :loading="loading"
                    @search="handleSearchNearby"
                />
            </div>

            <div
                ref="panelScroll"
                :class="[
                    'min-h-0 flex-1 [scrollbar-width:thin] [scrollbar-color:#dfe6ef_transparent] overflow-auto',
                    'overscroll-contain max-md:group-data-[sheet=collapsed]/explorer:hidden',
                ]"
            >
                <!-- Owner listings, place details, or discovery results -->
                <OwnerWorkspace
                    v-if="workspace && owner"
                    :places="myBusinesses"
                    :trash="trashBusinesses"
                    :showing-trash="showingTrash"
                    :busy="busy"
                    @create="handleOpenDrawer()"
                    @edit="handleOpenDrawer"
                    @toggle-trash="handleToggleTrash"
                    @restore="handleRestoreListing"
                />

                <BusinessDetails
                    v-else-if="selected"
                    :place="selected"
                    @back="handleBackToResults"
                />

                <template v-else>
                    <div
                        class="flex items-center justify-between px-[22px] pt-5 pb-[15px] max-md:px-5 max-md:pt-2 max-md:pb-[14px]"
                    >
                        <div>
                            <h2
                                class="mx-0 mt-0 mb-1 text-[13px] font-[650] tracking-[-0.35px]"
                            >
                                {{
                                    searchArea
                                        ? 'Around your search'
                                        : 'Places to discover'
                                }}
                            </h2>

                            <span class="text-[10px] text-[#9aa5b6]">
                                {{
                                    loading
                                        ? 'Finding your next favorite…'
                                        : `${listings.length} local ${listings.length === 1 ? 'place' : 'places'}`
                                }}
                            </span>
                        </div>

                        <button
                            v-if="searchArea"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-[#f3f6fb] text-[#768ba5]"
                            aria-label="Clear search"
                            @click="handleResetSearch"
                        >
                            <X :size="17" />
                        </button>

                        <span
                            v-else
                            class="rounded-[6px] bg-[#f2f5fa] px-2 py-1 text-[10px] font-[700] text-[#64748b]"
                        >
                            {{ listings.length }}
                        </span>
                    </div>

                    <!-- Search failure and retry state -->
                    <div
                        v-if="error"
                        class="mx-5 mt-0 mb-[15px] rounded-[9px] bg-[#fff5ed] p-3 text-[12px] text-[#90652d]"
                        role="alert"
                    >
                        {{ error }}
                        <button
                            class="block border-0 bg-transparent px-0 pt-[6px] pb-0 font-[700] text-explorer-blue"
                            @click="handleRetrySearch"
                        >
                            Try again
                        </button>
                    </div>

                    <BusinessResults
                        :places="listings"
                        :loading="loading"
                        :can-expand="
                            !!searchArea && searchArea.radius < 1 && !error
                        "
                        @select="handleSelectBusiness"
                        @expand="handleExpandSearch"
                    />
                </template>

                <p
                    v-if="trashError"
                    class="rounded-[8px] bg-[#fff6e6] p-[10px] text-[11px] leading-[1.6] text-[#997432]"
                    role="alert"
                >
                    {{ trashError }}
                </p>
            </div>
        </aside>

        <!-- Shared create and edit drawer -->
        <ListingDrawer
            v-if="drawerOpen && owner"
            :listing="editing"
            :initial-location="userLocation"
            :categories="categories"
            @close="handleCloseDrawer"
        />
    </main>
</template>
