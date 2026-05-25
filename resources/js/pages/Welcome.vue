<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed, nextTick } from 'vue';
import BaseModal from '@/components/BaseModal.vue';
import BusinessCreateForm from '@/components/owner/BusinessCreateForm.vue';
import WelcomeSidebar from '@/components/WelcomeSidebar.vue';
import { useLeafletMap } from '@/composables/useLeafletMap';
import type { Category, Business, ListingPin } from '@/types';

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        categories?: Category[];
        allBusinesses?: Business[];
        myBusinesses?: Business[];
    }>(),
    {
        canRegister: false,
        categories: () => [],
        allBusinesses: () => [],
        myBusinesses: () => [],
    },
);

// Map States
const mapContainer = ref<HTMLDivElement | null>(null);
const selectedCategory = ref<string | number>('all');
const {
    isMapLoaded,
    activeLayer,
    initMap,
    renderMarkers,
    toggleLayer,
    panToLocation,
} = useLeafletMap(mapContainer);

// Modal and Form States
const isCreateModalOpen = ref(false);
const currentTab = ref('general');

// Categories Fallbacks Centered on Philippine Cities
const defaultCategories = [
    {
        id: 'all',
        name: 'All Services',
        icon: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        color: '#185FA5',
    },
    {
        id: 'food',
        name: 'Food & Cafes',
        icon: 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
        color: '#1D9E75',
    },
    {
        id: 'retail',
        name: 'Retail Shops',
        icon: 'M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0',
        color: '#0F6E56',
    },
    {
        id: 'services',
        name: 'Services',
        icon: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
        color: '#378ADD',
    },
    {
        id: 'accommodation',
        name: 'Hotels',
        icon: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
        color: '#EF9F27',
    },
];

const defaultListings = [
    {
        id: 1,
        name: 'Manila Bay Artisanal Cafe',
        category: 'food',
        lat: 14.5995,
        lng: 120.9842,
        rating: 4.8,
        reviewsCount: 42,
        color: '#1D9E75',
        description:
            'Freshly brewed local coffees & handcrafted pastries by the bay.',
    },
    {
        id: 2,
        name: 'Sugbo Seafood Bistro',
        category: 'food',
        lat: 10.3157,
        lng: 123.8854,
        rating: 4.6,
        reviewsCount: 28,
        color: '#1D9E75',
        description: 'Fresh ocean catch served in a cozy atmosphere.',
    },
    {
        id: 3,
        name: 'Davao Durian Retail Hub',
        category: 'retail',
        lat: 7.1907,
        lng: 125.4553,
        rating: 4.9,
        reviewsCount: 65,
        color: '#0F6E56',
        description: 'Premium durian products and local treats.',
    },
];

const categoriesList = computed(() => {
    return props.categories && props.categories.length > 0
        ? props.categories
        : defaultCategories;
});

const listingsList = computed(() => {
    return props.allBusinesses && props.allBusinesses.length > 0
        ? props.allBusinesses
        : defaultListings;
});

const page = usePage();
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

onMounted(() => {
    initMap(
        categoriesList.value,
        listingsList.value as ListingPin[],
        selectedCategory,
    );
});

const handleCategoryFilter = (categoryId: string | number) => {
    selectedCategory.value = categoryId;
    renderMarkers(
        categoriesList.value,
        listingsList.value as ListingPin[],
        selectedCategory,
    );
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
            class="absolute inset-0 z-0 h-full w-full"
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
            @filterCategory="handleCategoryFilter"
            @toggleLayer="handleLayerToggle"
            @panTo="handlePanTo"
            @openCreateModal="handleOpenCreateModal"
        />

        <!-- Reusable Glassmorphic Modal Popup -->
        <BaseModal
            :is-open="isCreateModalOpen"
            title="Register Business Directory"
            subtitle="Complete registration details to place custom pins on database mapping."
            :tabs="['general', 'details', 'schedule', 'position']"
            v-model:currentTab="currentTab"
            @close="handleCloseCreateModal"
        >
            <BusinessCreateForm
                :current-tab="currentTab"
                :categories-list="categoriesList"
                @close="handleCloseCreateModal"
                @tabChange="(tab) => (currentTab = tab)"
            />
        </BaseModal>

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
