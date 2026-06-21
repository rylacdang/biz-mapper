<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { onMounted, ref, computed, watch } from 'vue';
import CategoryExplorerModal from '@/components/CategoryExplorerModal.vue';
import BusinessCreateForm from '@/components/owner/BusinessCreateForm.vue';
import BusinessEditForm from '@/components/owner/BusinessEditForm.vue';
import BusinessTrashList from '@/components/owner/BusinessTrashList.vue';
import WelcomeSidebar from '@/components/WelcomeSidebar.vue';
import { useLeafletMap } from '@/composables/useLeafletMap';
import { useProximitySearch } from '@/composables/useProximitySearch';
import type { Category, Business, ListingPin } from '@/types';

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        categories?: Category[];
        allBusinesses?: Business[];
        myBusinesses?: Business[];
        selectedCategory?: string | number;
        trashBusinesses?: Business[];
    }>(),
    {
        canRegister: false,
        categories: () => [],
        allBusinesses: () => [],
        myBusinesses: () => [],
        selectedCategory: 'all',
        trashBusinesses: () => [],
    },
);

// Map States
const mapContainer = ref<HTMLDivElement | null>(null);
const selectedCategory = ref<string | number>(props.selectedCategory);
const userCoords = ref<{ lat: number; lng: number } | null>(null);
const {
    isMapLoaded,
    activeLayer,
    initMap,
    renderMarkers,
    toggleLayer,
    panToLocation,
    showUserLocation,
    getMapCenter,
    showProximityCircle,
    clearProximityCircle,
} = useLeafletMap(mapContainer);

// Modal and Form States
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const isTrashModalOpen = ref(false);
const isCategoryModalOpen = ref(false);
const selectedListing = ref<Business | null>(null);
const currentTab = ref('general');
const editTab = ref('general');

const categoriesList = computed(() => {
    return props.categories ?? [];
});

const listingsList = computed(() => {
    return props.allBusinesses ?? [];
});

const page = usePage();
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

// Watch selectedCategory prop from server to keep local ref in sync
watch(
    () => props.selectedCategory,
    (newVal) => {
        selectedCategory.value = newVal;
    },
);

// Watch dynamic Inertia partial reload updates of listingsList to redraw pins
watch(listingsList, () => {
    renderMarkers(
        categoriesList.value,
        listingsList.value as ListingPin[],
        selectedCategory,
    );
});

onMounted(async () => {
    await initMap(
        categoriesList.value,
        listingsList.value as ListingPin[],
        selectedCategory,
    );

    // Ask for user location to pan map to their area
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const { latitude, longitude } = position.coords;
                userCoords.value = { lat: latitude, lng: longitude };
                panToLocation(latitude, longitude, 13);
                showUserLocation(latitude, longitude);

                // Show the default 5km proximity circle around user position
                showProximityCircle(latitude, longitude, 5);
            },
            () => {
                // Denied or timeout — draw 5km circle around default center
                const center = getMapCenter();

                if (center) {
                    showProximityCircle(center.lat, center.lng, 5);
                }
            },
            { timeout: 8000, enableHighAccuracy: false },
        );
    } else {
        // Geolocation not supported — draw 5km circle around default center
        const center = getMapCenter();

        if (center) {
            showProximityCircle(center.lat, center.lng, 5);
        }
    }
});

const handleCategoryFilter = (categoryId: string | number) => {
    selectedCategory.value = categoryId;

    const params = categoryId === 'all' ? {} : { category: categoryId };

    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
        only: ['allBusinesses'],
    });
};

const handleLayerToggle = (layer: string) => {
    toggleLayer(layer);
};

const handlePanTo = ({ lat, lng }: { lat: number; lng: number }) => {
    panToLocation(lat, lng);
};

const handleOpenCreateModal = () => {
    isCreateModalOpen.value = true;
    currentTab.value = 'general';
};

const handleCloseCreateModal = () => {
    isCreateModalOpen.value = false;
};

const handleOpenEditModal = (biz: Business) => {
    selectedListing.value = biz;
    editTab.value = 'general';
    isEditModalOpen.value = true;
};

const handleCloseEditModal = () => {
    isEditModalOpen.value = false;
    selectedListing.value = null;
};

const handleOpenTrashModal = () => {
    isTrashModalOpen.value = true;
    router.reload({
        only: ['trashBusinesses'],
    });
};

const handleCloseTrashModal = () => {
    isTrashModalOpen.value = false;
};

const {
    searchResults,
    isNearbySearchActive,
    search: proximitySearch,
    reset: proximityReset,
} = useProximitySearch({
    categoriesList,
    listingsList,
    selectedCategory,
    renderMarkers,
    panToLocation,
    getMapCenter,
    showProximityCircle,
    clearProximityCircle,
});

const handleSearchProximity = (payload: {
    radius: number;
    keyword: string;
}) => {
    console.log('[Welcome] handleSearchProximity event received:', payload);
    proximitySearch(payload);
};

const handleRadiusChanged = (radius: number) => {
    console.log('[Welcome] handleRadiusChanged event received:', radius);

    if (userCoords.value) {
        showProximityCircle(userCoords.value.lat, userCoords.value.lng, radius);
    } else {
        const center = getMapCenter();

        if (center) {
            showProximityCircle(center.lat, center.lng, radius);
        }
    }
};

const handleResetSearch = () => {
    console.log('[Welcome] handleResetSearch event received');
    proximityReset();
};
</script>

<template>
    <Head title="Explore Philippines Hyperlocal">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="relative h-screen w-screen overflow-hidden bg-gray-900 font-['Plus_Jakarta_Sans'] text-gray-100 antialiased"
    >
        <!-- Leaflet Map Container -->
        <div
            ref="mapContainer"
            class="absolute inset-0 z-0 h-full w-full bg-zinc-900"
        ></div>

        <!-- Sleek Dark Overlay gradient -->
        <div
            class="pointer-events-none absolute inset-0 z-5 bg-gradient-to-r from-black/50 via-black/10 to-transparent"
        ></div>

        <!-- Sidebar Component -->
        <WelcomeSidebar
            :is-owner="isOwner"
            :can-register="props.canRegister"
            :categories="categoriesList"
            :my-businesses="props.myBusinesses"
            :selected-category="selectedCategory"
            :active-layer="activeLayer"
            :search-results="searchResults"
            :is-nearby-search-active="isNearbySearchActive"
            @filterCategory="handleCategoryFilter"
            @toggleLayer="handleLayerToggle"
            @panTo="handlePanTo"
            @openCreateModal="handleOpenCreateModal"
            @openEditModal="handleOpenEditModal"
            @openTrashModal="handleOpenTrashModal"
            @searchProximity="handleSearchProximity"
            @radiusChanged="handleRadiusChanged"
            @resetSearch="handleResetSearch"
            @openCategoryExplorer="isCategoryModalOpen = true"
        />

        <!-- Create Listing Modal -->
        <BusinessCreateForm
            v-if="isCreateModalOpen"
            :is-open="isCreateModalOpen"
            :current-tab="currentTab"
            :categories-list="categoriesList"
            @close="handleCloseCreateModal"
            @tabChange="(tab) => (currentTab = tab)"
        />

        <!-- Edit Listing Modal -->
        <BusinessEditForm
            v-if="selectedListing"
            :is-open="isEditModalOpen"
            :current-tab="editTab"
            :categories-list="categoriesList"
            :listing="selectedListing"
            @close="handleCloseEditModal"
            @tabChange="(tab) => (editTab = tab)"
        />

        <!-- Recycle Bin (Trash) Modal -->
        <BusinessTrashList
            v-if="isTrashModalOpen"
            :is-open="isTrashModalOpen"
            :trash-businesses="props.trashBusinesses"
            @close="handleCloseTrashModal"
        />

        <!-- Category Explorer Modal -->
        <CategoryExplorerModal
            :is-open="isCategoryModalOpen"
            :categories="categoriesList"
            :selected-category="selectedCategory"
            @close="isCategoryModalOpen = false"
            @selectCategory="handleCategoryFilter"
        />

        <!-- Dynamic loader overlay until map loads -->
        <div
            v-if="!isMapLoaded"
            class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-gray-900 transition-opacity duration-300"
        >
            <div class="relative mb-4 h-14 w-14 animate-bounce">
                <img
                    src="/img/biz-mapper.png"
                    alt="BizMapper Logo"
                    class="relative z-10 h-full w-full object-contain"
                />
            </div>
            <p
                class="animate-pulse text-xs font-bold tracking-widest text-gray-500 uppercase"
            >
                Initializing Hyperlocal Map...
            </p>
        </div>
    </div>
</template>

<style>
/* Perfect Custom Leaflet Glassmorphic Popups styling */
.custom-leaflet-popup .leaflet-popup-content-wrapper {
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(12px) !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    border-radius: 14px !important;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.15),
        0 10px 10px -5px rgba(0, 0, 0, 0.1) !important;
    padding: 0 !important;
    overflow: hidden !important;
}

.custom-leaflet-popup .leaflet-popup-content {
    margin: 0 !important;
}

.custom-leaflet-popup .leaflet-popup-tip {
    background: rgba(255, 255, 255, 0.9) !important;
    backdrop-filter: blur(12px) !important;
    box-shadow: none !important;
}

.leaflet-container .leaflet-control-attribution {
    background: rgba(9, 9, 11, 0.8) !important;
    backdrop-filter: blur(6px) !important;
    color: #a1a1aa !important;
    border-radius: 6px 0 0 0 !important;
    font-size: 8px !important;
    border-left: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
}

.leaflet-container .leaflet-control-attribution a {
    color: #818cf8 !important;
}

.leaflet-bar {
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

.leaflet-bar a {
    background-color: #18181b !important;
    color: #f4f4f5 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    transition: all 0.2s !important;
}

.leaflet-bar a:hover {
    background-color: #27272a !important;
    color: #ffffff !important;
}
</style>
