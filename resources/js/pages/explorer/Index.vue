<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Bookmark, ChevronDown, ChevronUp, Store, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import BusinessResults from '@/components/businesses/BusinessResults.vue';
import { useExplorerPanel } from '@/composables/useExplorerPanel';
import { useExplorerSearch } from '@/composables/useExplorerSearch';
import { useOwnerWorkspace } from '@/composables/useOwnerWorkspace';
import { useWalkingRoute } from '@/composables/useWalkingRoute';
import { DEFAULT_CENTER } from '@/constants/map';
import BusinessDetails from '@/pages/explorer/partials/BusinessDetails.vue';
import CityMap from '@/pages/explorer/partials/CityMap.vue';
import ExplorerSearch from '@/pages/explorer/partials/ExplorerSearch.vue';
import ExplorerToolbar from '@/pages/explorer/partials/ExplorerToolbar.vue';
import ListingDrawer from '@/pages/explorer/partials/listings/ListingDrawer.vue';
import OwnerWorkspace from '@/pages/explorer/partials/OwnerWorkspace.vue';
import SavedPlaces from '@/pages/explorer/partials/SavedPlaces.vue';
import SharedCollection from '@/pages/explorer/partials/SharedCollection.vue';
import { index as explorerIndex } from '@/routes/explorer';
import type {
    Business,
    BusinessInsights,
    OwnerListingPagination,
    Category,
    Coordinates,
    DiscoveryShortcut,
    SearchBusiness,
    SavedPlaceChange,
    PublicCollection,
    PublicCollectionPlacesPage,
} from '@/types';

// Page data and discovery state
const props = withDefaults(
    defineProps<{
        categories?: Category[];
        discoveryShortcuts?: DiscoveryShortcut[];
        offeringCurrencies?: string[];
        myBusinesses?: Business[];
        ownerInsights?: Record<number, BusinessInsights>;
        ownerPagination?: OwnerListingPagination;
        trashBusinesses?: Business[];
        canRegister?: boolean;
        directionsBusiness?: SearchBusiness | null;
        sharedCollection?: PublicCollection | null;
        sharedPlaces?: PublicCollectionPlacesPage | null;
    }>(),
    {
        categories: () => [],
        discoveryShortcuts: () => [],
        offeringCurrencies: () => [],
        myBusinesses: () => [],
        ownerInsights: () => ({}),
        ownerPagination: () => ({
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
            from: null,
            to: null,
            query: '',
            status: 'all',
        }),
        trashBusinesses: () => [],
        canRegister: false,
        directionsBusiness: null,
        sharedCollection: null,
        sharedPlaces: null,
    },
);

const page = usePage();
const owner = computed(() => page.props.auth?.user?.role === 'owner');
const consumer = computed(() => page.props.auth?.user?.role === 'consumer');
const savedView = ref(false);
const sharedView = ref(Boolean(props.sharedCollection));
const sharedLoading = ref(false);
const sharedError = ref('');
let sharedRequestedPage = props.sharedPlaces?.meta.current_page ?? 1;
let cancelSharedVisit: (() => void) | undefined;
const collectionPlaces = ref<SearchBusiness[] | null>(null);
const collectionFocus = ref<Coordinates[] | null>(null);
const activeCollectionPlaces = computed(() =>
    sharedView.value
        ? (props.sharedPlaces?.data ?? [])
        : (collectionPlaces.value ?? []),
);
const showingCollection = computed(
    () =>
        sharedView.value ||
        (savedView.value && collectionPlaces.value !== null),
);
const visibleMarkers = computed(() =>
    showingCollection.value
        ? activeCollectionPlaces.value.map((place) => ({
              id: place.id,
              slug: place.slug,
              lat: place.lat,
              lng: place.lng,
              category_id: place.category?.id ?? null,
          }))
        : markers.value,
);

const {
    workspace,
    showingTrash,
    busy,
    trashError,
    drawerOpen,
    editing,
    drawerStep,
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
const walking = useWalkingRoute(() => userLocation.value);
const { route: walkingRoute, loading: routing, error: routeError } = walking;

watch(
    () => selected.value?.slug,
    (slug) => {
        if (walking.destination.value?.slug !== slug) {
            walking.clear();
        }
    },
);

function handleDirections() {
    if (selected.value) {
        void walking.start(selected.value);
    }
}

function handleClearRoute() {
    walking.clear();

    const url = new URL(window.location.href);

    if (!url.searchParams.has('destination')) {
        return;
    }

    url.searchParams.delete('destination');

    // Replace the directions entry so refresh and Inertia history stay in sync.
    router.replace({
        url: `${url.pathname}${url.search}${url.hash}`,
        props: (currentProps) => ({
            ...currentProps,
            directionsBusiness: null,
        }),
        preserveState: true,
        preserveScroll: true,
    });
}

const searchState = useExplorerSearch(
    () => center.value,
    () => userLocation.value,
);

const {
    keyword,
    category,
    shortcut,
    radius,
    listings,
    markers,
    pagination,
    selecting,
    loading,
    error,
    lastSearch,
    searchArea,
    visibleSearchArea,
    moved,
} = searchState;

const initialCollectionPlace = sharedView.value
    ? props.sharedPlaces?.data[0]
    : null;
const initialCenter = initialCollectionPlace
    ? { lat: initialCollectionPlace.lat, lng: initialCollectionPlace.lng }
    : searchState.initialCenter;
center.value = initialCenter;
const mapCenter = ref<Coordinates>(initialCenter);

// Discovery and panel actions
async function handleSelectBusiness(id: number) {
    const collectionPlace = showingCollection.value
        ? activeCollectionPlaces.value.find((place) => place.id === id)
        : null;

    if (collectionPlace) {
        handleSelectSavedBusiness(collectionPlace);

        return;
    }

    if (selected.value?.id === id) {
        panelOpen.value = true;

        return;
    }

    savedView.value = false;
    workspace.value = false;
    panelOpen.value = true;
    sheet.value = 'half';

    const place = await searchState.findBusiness(id);

    if (place) {
        selected.value = place;
        sheet.value = 'half';
        panelOpen.value = true;
    }
}

watch(panelOpen, (open) => {
    if (!open) {
        searchState.cancelSelection();
    }
});

function handleSearchNearby() {
    if (sharedView.value) {
        router.get(explorerIndex.url(), {
            ...center.value,
            keyword: keyword.value,
            category: category.value,
            radius: radius.value,
        });

        return;
    }

    savedView.value = false;

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
    savedView.value = false;

    selected.value = null;
    searchState.search();
}

function handleLocate(location: Coordinates) {
    if (sharedView.value) {
        router.get(explorerIndex.url(), {
            ...location,
            keyword: '',
            category: 'all',
            radius: radius.value,
        });

        return;
    }

    if (walking.destination.value) {
        return;
    }

    savedView.value = false;
    center.value = location;
    panelOpen.value = true;
    workspace.value = false;
    selected.value = null;
    sheet.value = 'half';
    searchState.search({
        ...location,
        keyword: keyword.value,
        category: category.value,
        shortcut: shortcut.value || undefined,
        radius: radius.value,
    });
}

function handleBackToResults() {
    selected.value = null;

    if (savedView.value) {
        void nextTick(() => {
            panelScroll.value
                ?.querySelector<HTMLElement>(
                    '[aria-label="Your saved places"] h2',
                )
                ?.focus({ preventScroll: true });
        });
    }
}

function handleShowListings() {
    showOwnerListings();
    selected.value = null;
}

function handleResetSearch() {
    searchState.reset();
}

function handleRetrySearch() {
    searchState.retry();
}

function handleExpandSearch() {
    radius.value = 1;

    if (searchArea.value) {
        searchState.search({ ...searchArea.value, radius: 1 });
    }
}

function handleOpenExplore() {
    if (sharedView.value) {
        router.visit(explorerIndex.url());

        return;
    }

    savedView.value = false;
    collectionFocus.value = null;
    collectionPlaces.value = null;
    handleOpenPanel();
}

function handleOpenSaved() {
    if (sharedView.value) {
        router.get(
            explorerIndex.url(),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    sharedView.value = false;
                    handleOpenSaved();
                },
            },
        );

        return;
    }

    if (!consumer.value) {
        return;
    }

    searchState.cancelSelection();
    handleOpenPanel();
    savedView.value = true;
}

function handleSelectSavedBusiness(business: SearchBusiness) {
    searchState.cancelSelection();
    selected.value = business;
    panelOpen.value = true;
    sheet.value = 'half';
}

function handleCollectionChange(places: SearchBusiness[] | null) {
    collectionPlaces.value = places;
    collectionFocus.value = null;
}

function handleShowCollectionMap(places: SearchBusiness[]) {
    selected.value = null;
    sheet.value = 'half';
    collectionFocus.value = places.map(({ lat, lng }) => ({ lat, lng }));
}

function activateSharedCollection() {
    if (!props.sharedCollection || !props.sharedPlaces) {
        sharedView.value = false;
        collectionFocus.value = null;

        return;
    }

    searchState.cancelSearch();
    workspace.value = false;
    savedView.value = false;
    sharedView.value = true;
    selected.value = null;
    panelOpen.value = true;
    sheet.value = 'half';
    sharedError.value = '';
    sharedRequestedPage = props.sharedPlaces.meta.current_page;
    const first = props.sharedPlaces.data[0];

    if (first) {
        mapCenter.value = { lat: first.lat, lng: first.lng };
        center.value = mapCenter.value;
    }

    collectionFocus.value = props.sharedPlaces.data.map(({ lat, lng }) => ({
        lat,
        lng,
    }));
}

function handleSharedFailure() {
    sharedError.value =
        'Could not load this collection. It may no longer be public. Please try again.';

    return false;
}

function handleSharedPage(page: number) {
    if (!props.sharedCollection) {
        return;
    }

    sharedRequestedPage = page;
    sharedLoading.value = true;
    sharedError.value = '';
    router.get(
        explorerIndex.url(),
        {
            collection: props.sharedCollection.share_token,
            collection_page: page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onCancelToken: (token) => {
                cancelSharedVisit = () => token.cancel();
            },
            onError: handleSharedFailure,
            onHttpException: handleSharedFailure,
            onNetworkError: handleSharedFailure,
            onFinish: () => {
                sharedLoading.value = false;
                cancelSharedVisit = undefined;
            },
        },
    );
}

watch(
    () => [
        props.sharedCollection?.share_token,
        props.sharedPlaces?.meta.current_page,
    ],
    activateSharedCollection,
);

// Update existing records so switching between results and details keeps the bookmark state.
function handleSavedChange(event: Event) {
    const change = (event as CustomEvent<SavedPlaceChange>).detail;

    if (change.userId !== page.props.auth?.user?.id) {
        return;
    }

    for (const place of [...listings.value, ...activeCollectionPlaces.value]) {
        if (place.id === change.businessId) {
            place.is_saved = change.saved;
        }
    }

    if (selected.value?.id === change.businessId) {
        selected.value.is_saved = change.saved;
    }
}

watch(
    () => page.props.auth?.user?.id,
    () => {
        savedView.value = false;
        collectionPlaces.value = null;
        collectionFocus.value = null;
        selected.value = null;

        for (const place of listings.value) {
            place.is_saved = false;
        }
    },
);

// Reactive synchronization and navigation lifecycle
watch(lastSearch, (state) => {
    // Clearing results leaves the camera at its current position.
    if (
        state &&
        !sharedView.value &&
        (state.lat !== mapCenter.value.lat || state.lng !== mapCenter.value.lng)
    ) {
        mapCenter.value = { lat: state.lat, lng: state.lng };
    }
});

watch(markers, (places) => {
    if (
        selected.value &&
        !savedView.value &&
        !sharedView.value &&
        walking.destination.value?.id !== selected.value.id &&
        !places.some((p) => p.id === selected.value!.id)
    ) {
        selected.value = null;
    }
});

// Restore filters on Inertia history changes without adding another history entry.
const removeNavigation = router.on('navigate', (event) => {
    if (!event.detail.page.props.sharedCollection) {
        searchState.restore(event.detail.page.url);
    }
});

onMounted(() => {
    window.addEventListener('saved-place-changed', handleSavedChange);

    if (props.sharedCollection) {
        activateSharedCollection();

        return;
    }

    searchState.restore(window.location.href);

    if (props.directionsBusiness) {
        selected.value = props.directionsBusiness;
        panelOpen.value = true;
        sheet.value = 'half';
        handleDirections();
    }
});

onUnmounted(() => {
    removeNavigation();
    cancelSharedVisit?.();
    window.removeEventListener('saved-place-changed', handleSavedChange);
});
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
            '[&_button:disabled]:cursor-not-allowed [&_button:disabled]:opacity-55',
            '[&_h1]:font-explorer-heading [&_h2]:font-explorer-heading [&_h3]:font-explorer-heading',
        ]"
        :data-sheet="sheet"
        :data-panel-open="panelOpen"
    >
        <!-- Full-screen city map -->
        <CityMap
            :panel-open="panelOpen"
            :places="visibleMarkers"
            :categories="categories"
            :center="mapCenter"
            :selected="selected"
            :route="walkingRoute"
            :radius="showingCollection ? null : visibleSearchArea"
            :collection-focus="showingCollection ? collectionFocus : null"
            :collection-view="sharedView"
            :moved="moved && !workspace && !showingCollection"
            @select="handleSelectBusiness"
            @move="handleMapMove"
            @search="handleSearchMapArea"
            @locate="handleLocate"
            @user-location="userLocation = $event"
        />

        <!-- Floating search, account, and owner controls -->
        <ExplorerToolbar
            v-model:keyword="keyword"
            :count="
                sharedView ? (sharedPlaces?.meta.total ?? 0) : pagination.total
            "
            :loading="loading"
            :owner="owner && !sharedView"
            :panel-open="panelOpen"
            :can-register="canRegister"
            @search="handleSearchNearby"
            @explore="handleOpenExplore"
            @saved="handleOpenSaved"
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
                'max-md:top-auto max-md:bottom-0 max-md:left-0 max-md:h-[52dvh] max-md:w-full',
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
                                  : sharedView
                                    ? 'Shared collection'
                                    : savedView
                                      ? 'Saved places'
                                      : 'Explore nearby'
                        }}
                    </h1>

                    <p class="mt-0.5 text-[10px] text-slate-400">
                        {{
                            workspace
                                ? 'Manage your local presence'
                                : sharedView
                                  ? 'Explore places from this shared list'
                                  : savedView
                                    ? 'Your own neighborhood favorites'
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
                v-if="owner && !sharedView"
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

            <nav
                v-if="consumer && !sharedView"
                class="mx-5 mt-4 flex gap-1 rounded-xl bg-slate-100 p-1 max-md:shrink-0 max-md:group-data-[sheet=collapsed]/explorer:hidden"
                aria-label="Explorer workspace"
            >
                <button
                    type="button"
                    :aria-pressed="!savedView"
                    :class="[
                        'h-9 flex-1 rounded-lg text-[11px] font-semibold',
                        !savedView
                            ? 'bg-white text-explorer-blue shadow-sm'
                            : 'text-slate-500',
                    ]"
                    @click="handleOpenExplore"
                >
                    Explore
                </button>

                <button
                    type="button"
                    :aria-pressed="savedView"
                    :class="[
                        'flex h-9 flex-1 items-center justify-center gap-2 rounded-lg text-[11px] font-semibold',
                        savedView
                            ? 'bg-white text-explorer-blue shadow-sm'
                            : 'text-slate-500',
                    ]"
                    @click="handleOpenSaved"
                >
                    <Bookmark :size="14" /> Saved places
                </button>
            </nav>

            <div
                ref="panelScroll"
                :class="[
                    'min-h-0 flex-1 [scrollbar-width:thin] [scrollbar-color:#dfe6ef_transparent] overflow-auto',
                    'overscroll-contain max-md:group-data-[sheet=collapsed]/explorer:hidden',
                ]"
            >
                <!-- Category and radius filters -->
                <div
                    v-if="!workspace && !selected && !savedView && !sharedView"
                    class="shrink-0 pt-2 max-md:group-data-[sheet=collapsed]/explorer:hidden"
                >
                    <ExplorerSearch
                        :show-keyword="false"
                        v-model:keyword="keyword"
                        v-model:category="category"
                        v-model:radius="radius"
                        v-model:shortcut="shortcut"
                        :shortcuts="discoveryShortcuts"
                        :categories="categories"
                        :loading="loading"
                        @search="handleSearchNearby"
                        @clear-shortcut="searchState.clearShortcut"
                    />
                </div>

                <p
                    v-if="selecting"
                    role="status"
                    class="px-5 py-3 text-xs text-explorer-blue"
                >
                    Loading place details�
                </p>

                <!-- Owner listings, place details, or discovery results -->
                <OwnerWorkspace
                    v-if="workspace && owner"
                    :places="myBusinesses"
                    :insights="ownerInsights"
                    :pagination="ownerPagination"
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
                    :route="walkingRoute"
                    :routing="routing"
                    :route-error="routeError"
                    @directions="handleDirections"
                    @clear-route="handleClearRoute"
                    @back="handleBackToResults"
                />

                <SharedCollection
                    v-else-if="sharedView && sharedCollection && sharedPlaces"
                    :collection="sharedCollection"
                    :places="sharedPlaces"
                    :loading="sharedLoading"
                    :error="sharedError"
                    @select="handleSelectBusiness"
                    @page-change="handleSharedPage"
                    @retry="handleSharedPage(sharedRequestedPage)"
                    @show-map="handleShowCollectionMap(sharedPlaces.data)"
                />

                <template v-else-if="!savedView">
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
                                        : `${pagination.total} local ${pagination.total === 1 ? 'place' : 'places'}`
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
                            {{ pagination.total }}
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
                        :pagination="pagination"
                        :can-expand="
                            !!searchArea && searchArea.radius < 1 && !error
                        "
                        @select="handleSelectBusiness"
                        @expand="handleExpandSearch"
                        @page-change="searchState.changePage"
                    />
                </template>

                <SavedPlaces
                    v-if="savedView && consumer"
                    v-show="!selected"
                    @select="handleSelectSavedBusiness"
                    @collection-change="handleCollectionChange"
                    @show-map="handleShowCollectionMap"
                />

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
            :initial-step="drawerStep"
            :currencies="offeringCurrencies"
            :initial-location="userLocation"
            :categories="categories"
            @close="handleCloseDrawer"
        />
    </main>
</template>
