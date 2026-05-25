<script setup lang="ts">
import { dashboard, login, register, logout } from '@/routes';
import business from '@/routes/owner/business';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed, nextTick } from 'vue';

const props = withDefaults(
    defineProps<{
        canRegister?: boolean;
        categories?: Array<{
            id: number;
            name: string;
            icon: string;
            color: string;
        }>;
        allBusinesses?: Array<{
            id: number;
            user_id: number;
            category_id: number;
            name: string;
            slug: string;
            description: string;
            address: string;
            lat: number;
            lng: number;
            phone: string;
            website: string;
            cover_photo: string;
            logo: string;
            hours: any;
        }>;
        myBusinesses?: Array<{
            id: number;
            name: string;
            address: string;
            cover_photo: string;
            lat: number;
            lng: number;
        }>;
    }>(),
    {
        canRegister: false,
        categories: () => [],
        allBusinesses: () => [],
        myBusinesses: () => [],
    },
);

const mapContainer = ref<HTMLDivElement | null>(null);
const isMapLoaded = ref(false);
const selectedCategory = ref<string | number>('all');
const activeLayer = ref('satellite');

// Modal Form State
const isCreateModalOpen = ref(false);
const currentTab = ref('general'); // general, details, schedule, position
const modalMapContainer = ref<HTMLDivElement | null>(null);

// Category options
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

// Interactive mock listings centered on Philippine cities
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
        description: 'Fresh ocean catch served in a cozy modern atmosphere.',
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
        description:
            'Premium durian products, local treats, and native handicrafts.',
    },
    {
        id: 4,
        name: 'Boracay Horizon Resort',
        category: 'accommodation',
        lat: 11.9674,
        lng: 121.9248,
        rating: 4.7,
        reviewsCount: 110,
        color: '#EF9F27',
        description:
            'Breathtaking beachside views and premium boutique suites.',
    },
    {
        id: 5,
        name: 'Palawan Eco-Adventures',
        category: 'services',
        lat: 9.7392,
        lng: 118.7353,
        rating: 4.9,
        reviewsCount: 88,
        color: '#378ADD',
        description:
            'Guided underground river tours and responsible island hopping.',
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

// Form Initialization
const form = useForm({
    category_id: '',
    name: '',
    description: '',
    address: '',
    lat: 14.5995, // Default Manila
    lng: 120.9842,
    phone: '',
    website: '',
    cover_photo: null as File | null,
    logo: null as File | null,
    hours: {
        monday: { open: '09:00', close: '18:00', closed: false },
        tuesday: { open: '09:00', close: '18:00', closed: false },
        wednesday: { open: '09:00', close: '18:00', closed: false },
        thursday: { open: '09:00', close: '18:00', closed: false },
        friday: { open: '09:00', close: '18:00', closed: false },
        saturday: { open: '10:00', close: '16:00', closed: false },
        sunday: { open: '00:00', close: '00:00', closed: true },
    },
});

let L: any = null;
let map: any = null;
let markerLayerGroup: any = null;
let modalMap: any = null;
let modalMarker: any = null;

onMounted(async () => {
    // Dynamic load local Leaflet package and its CSS (SSR safe)
    const LeafletModule = await import('leaflet');
    L = LeafletModule.default || LeafletModule;
    await import('leaflet/dist/leaflet.css');

    initMap();
});

const initMap = () => {
    if (!L || !mapContainer.value) return;

    // Philippines Center Coordinates
    map = L.map(mapContainer.value, {
        center: [12.8797, 121.774],
        zoom: 6,
        zoomControl: false,
    });

    // Custom positioned zoom controls
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Esri Satellite layers
    const satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            attribution:
                'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        },
    );

    const labelsLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        {
            attribution: '',
        },
    );

    // Street view fallback
    const streetLayer = L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
    );

    const hybridGroup = L.layerGroup([satelliteLayer, labelsLayer]);

    // Add default satellite hybrid layer
    hybridGroup.addTo(map);

    // Save references globally on window
    (window as any).mapLayers = {
        satellite: hybridGroup,
        street: streetLayer,
    };

    markerLayerGroup = L.layerGroup().addTo(map);
    renderMarkers();
    isMapLoaded.value = true;
};

const renderMarkers = () => {
    if (!L || !map || !markerLayerGroup) return;

    markerLayerGroup.clearLayers();

    listingsList.value.forEach((listing) => {
        const listingCategory = listing.category_id || listing.category;

        if (
            selectedCategory.value !== 'all' &&
            listingCategory !== selectedCategory.value
        ) {
            return;
        }

        const categoryObj = categoriesList.value.find(
            (c) =>
                c.id === listingCategory ||
                c.id.toString() === listingCategory.toString(),
        );
        const pinColor = categoryObj
            ? categoryObj.color
            : listing.color || '#185FA5';
        const iconPath = categoryObj
            ? categoryObj.icon
            : 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z';

        // Beautiful custom glowing HTML map pins matching category color
        const iconHtml = `
            <div class="relative flex items-center justify-center cursor-pointer" style="width: 36px; height: 36px;">
                <span class="absolute inline-flex h-9 w-9 animate-ping rounded-full opacity-35" style="background-color: ${pinColor};"></span>
                <div class="relative flex items-center justify-center h-7 w-7 rounded-full border-2 border-white shadow-xl text-white transform hover:scale-110 transition-transform duration-200" style="background-color: ${pinColor};">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="${iconPath}" />
                    </svg>
                </div>
            </div>
        `;

        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-div-icon',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
        });

        // Fully styled glassmorphic popups matching high-end design aesthetics
        const ratingVal = listing.rating || 5.0;
        const reviewsCountVal =
            listing.reviewsCount !== undefined ? listing.reviewsCount : 0;
        const categoryName = categoryObj ? categoryObj.name : listingCategory;
        const addressText = listing.address || 'Philippines';

        const starsHtml =
            '★'.repeat(Math.round(ratingVal)) +
            '☆'.repeat(5 - Math.round(ratingVal));
        const popupContent = `
            <div class="p-3.5 max-w-[250px] font-sans">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="inline-block px-2 py-0.5 text-[9px] font-bold tracking-wider text-white uppercase rounded-md shadow-sm" style="background-color: ${pinColor};">
                        ${categoryName}
                    </span>
                    <span class="text-[10px] text-zinc-400 font-medium">${listing.phone ? listing.phone : 'No Phone'}</span>
                </div>
                <h4 class="m-0 text-sm font-bold text-zinc-900 leading-snug">${listing.name}</h4>
                <div class="flex items-center gap-1 my-1 text-xs text-amber-500">
                    <span class="font-bold text-zinc-800">${ratingVal}</span>
                    <span>${starsHtml}</span>
                    <span class="text-zinc-400 font-normal">(${reviewsCountVal})</span>
                </div>
                <p class="m-0 text-xs text-zinc-600 leading-relaxed font-normal">${listing.description}</p>
                <div class="mt-3 pt-2.5 border-t border-zinc-100 flex items-center justify-end">
                    <a href="/businesses/${listing.slug || listing.id}" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider flex items-center gap-0.5">
                        View Directory &rarr;
                    </a>
                </div>
            </div>
        `;

        const marker = L.marker([listing.lat, listing.lng], {
            icon: customIcon,
        });
        marker.bindPopup(popupContent, {
            maxWidth: 260,
            closeButton: false,
            className: 'custom-leaflet-popup',
        });

        marker.addTo(markerLayerGroup);
    });
};

const filterCategory = (categoryId: string | number) => {
    selectedCategory.value = categoryId;
    renderMarkers();
};

const toggleLayer = (layer: string) => {
    if (!map || !(window as any).mapLayers) return;

    activeLayer.value = layer;
    const layers = (window as any).mapLayers;

    if (layer === 'satellite') {
        map.removeLayer(layers.street);
        layers.satellite.addTo(map);
    } else {
        map.removeLayer(layers.satellite);
        layers.street.addTo(map);
    }
};

const panToBusiness = (lat: number, lng: number) => {
    if (map) {
        map.setView([lat, lng], 15, { animate: true, duration: 1.5 });
    }
};

// Modal Actions
const openCreateModal = () => {
    isCreateModalOpen.value = true;
    currentTab.value = 'general';
    form.reset();
    nextTick(initModalMap);
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
    }
};

const initModalMap = () => {
    if (!L || !modalMapContainer.value) return;

    modalMap = L.map(modalMapContainer.value, {
        center: [form.lat, form.lng],
        zoom: 12,
        zoomControl: false,
    });

    L.control.zoom({ position: 'bottomright' }).addTo(modalMap);

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
    ).addTo(modalMap);

    const pinColor = '#185FA5';
    const iconHtml = `
        <div class="relative flex items-center justify-center cursor-pointer" style="width: 36px; height: 36px;">
            <div class="relative flex items-center justify-center h-8 w-8 rounded-full border-2 border-white shadow-xl text-white bg-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="10" r="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
            </div>
        </div>
    `;

    const customIcon = L.divIcon({
        html: iconHtml,
        className: 'custom-div-icon',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
    });

    modalMarker = L.marker([form.lat, form.lng], {
        icon: customIcon,
        draggable: true,
    }).addTo(modalMap);

    modalMarker.on('dragend', () => {
        const position = modalMarker.getLatLng();
        form.lat = Number(position.lat.toFixed(7));
        form.lng = Number(position.lng.toFixed(7));
    });

    modalMap.on('click', (e: any) => {
        const position = e.latlng;
        modalMarker.setLatLng(position);
        form.lat = Number(position.lat.toFixed(7));
        form.lng = Number(position.lng.toFixed(7));
    });
};

const handleCoverUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.cover_photo = target.files[0];
    }
};

const handleLogoUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
    }
};

const submitBusinessForm = () => {
    form.post(business.store().url, {
        onSuccess: () => {
            closeCreateModal();
        },
    });
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

        <!-- Sleek Dark Overlay gradient to make left sidebar readable -->
        <div
            class="pointer-events-none absolute inset-0 z-5 bg-gradient-to-r from-black/50 via-black/10 to-transparent"
        ></div>

        <!-- Premium Glassmorphic Left Sidebar Panel -->
        <aside
            class="pointer-events-auto absolute top-4 bottom-4 left-4 z-10 flex max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] flex-col gap-6 overflow-y-auto rounded-2xl border border-gray-500/30 bg-gray-900/80 p-6 shadow-[0_20px_50px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:w-96"
        >
            <!-- Branding Header -->
            <div
                class="flex items-center justify-between border-b border-gray-500/10 pb-4"
            >
                <div class="flex items-center gap-3">
                    <div class="relative h-10 w-10 flex-shrink-0">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-xl bg-accent-light opacity-25"
                        ></span>
                        <img
                            src="/img/biz-mapper.png"
                            alt="BizMapper Logo"
                            class="relative z-10 h-full w-full object-contain"
                        />
                    </div>
                    <div>
                        <h1
                            class="bg-gradient-to-r from-gray-100 to-primary-light bg-clip-text text-xl font-extrabold tracking-tight text-transparent"
                        >
                            BizMapper
                        </h1>
                        <p
                            class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase"
                        >
                            {{
                                isOwner
                                    ? 'Owner Dashboard'
                                    : 'Hyperlocal Discovery'
                            }}
                        </p>
                    </div>
                </div>
                <!-- Sign Out if logged in as owner -->
                <Link
                    v-if="isOwner"
                    :href="logout()"
                    method="post"
                    as="button"
                    class="rounded-xl border border-gray-500/30 bg-gray-900 px-3 py-2 text-xs font-bold text-gray-100 transition-all hover:bg-gray-900/70 hover:text-white"
                >
                    Sign Out
                </Link>
            </div>

            <!-- OWNER-SPECIFIC SIDEBAR PANEL -->
            <template v-if="isOwner">
                <!-- Stats/Performance Cards -->
                <div class="grid grid-cols-2 gap-3">
                    <div
                        class="rounded-xl border border-gray-500/20 bg-gray-900/30 p-3"
                    >
                        <span
                            class="block text-lg leading-none font-extrabold text-gray-100"
                            >{{ props.myBusinesses.length }}</span
                        >
                        <span
                            class="mt-1.5 block text-[9px] font-semibold tracking-wider text-gray-500 uppercase"
                            >My Listings</span
                        >
                    </div>
                    <div
                        class="rounded-xl border border-gray-500/20 bg-gray-900/30 p-3"
                    >
                        <span
                            class="block text-lg leading-none font-extrabold text-primary-light"
                            >Active</span
                        >
                        <span
                            class="mt-1.5 block text-[9px] font-semibold tracking-wider text-gray-500 uppercase"
                            >Listing Status</span
                        >
                    </div>
                </div>

                <!-- Add Listing Main Action Button -->
                <button
                    @click="openCreateModal"
                    class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-center text-xs font-bold text-white shadow-lg shadow-primary/10 transition-all hover:bg-primary-light active:scale-98"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Register New Business
                </button>

                <!-- Dynamic Category Chips Filter -->
                <div class="space-y-3 border-t border-gray-500/20 pt-4">
                    <h3
                        class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                    >
                        Map Category Filter
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            @click="filterCategory('all')"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                            :class="[
                                selectedCategory === 'all'
                                    ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                    : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                            ]"
                        >
                            All Services
                        </button>
                        <button
                            v-for="cat in categoriesList"
                            :key="cat.id"
                            @click="filterCategory(cat.id)"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                            :class="[
                                selectedCategory === cat.id
                                    ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                    : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                            ]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3 w-3"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path :d="cat.icon" />
                            </svg>
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Owner Locations Manager Section -->
                <div
                    class="flex-1 space-y-3 overflow-y-auto border-t border-gray-500/20 pt-4"
                >
                    <h3
                        class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                    >
                        Manage My Locations
                    </h3>
                    <div
                        v-if="props.myBusinesses.length === 0"
                        class="rounded-xl border border-dashed border-gray-500/20 bg-gray-900/30 p-6 text-center"
                    >
                        <p class="text-xs leading-relaxed text-gray-500">
                            No registered locations found. Click the button
                            above to add your first business listing.
                        </p>
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="biz in props.myBusinesses"
                            :key="biz.id"
                            @click="panToBusiness(biz.lat, biz.lng)"
                            class="group flex cursor-pointer items-center gap-3 rounded-xl border border-gray-500/20 bg-gray-900/30 p-2.5 transition duration-200 hover:border-gray-500/40 hover:bg-gray-900/50"
                        >
                            <div
                                class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg bg-gray-800"
                            >
                                <img
                                    :src="
                                        biz.cover_photo
                                            ? '/storage/' + biz.cover_photo
                                            : '/img/placeholder.jpg'
                                    "
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4
                                    class="truncate text-xs font-bold text-gray-200 group-hover:text-white"
                                >
                                    {{ biz.name }}
                                </h4>
                                <p
                                    class="mt-0.5 truncate text-[10px] text-gray-500"
                                >
                                    {{ biz.address }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map View Mode Switcher -->
                <div class="space-y-3 border-t border-gray-500/20 pt-4">
                    <h3
                        class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                    >
                        Map View Mode
                    </h3>
                    <div
                        class="grid grid-cols-2 gap-2 rounded-xl border border-gray-500/20 bg-gray-900/30 p-1"
                    >
                        <button
                            @click="toggleLayer('satellite')"
                            class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                            :class="
                                activeLayer === 'satellite'
                                    ? 'bg-primary text-white shadow'
                                    : 'text-gray-500 hover:text-gray-100'
                            "
                        >
                            Satellite
                        </button>
                        <button
                            @click="toggleLayer('street')"
                            class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                            :class="
                                activeLayer === 'street'
                                    ? 'bg-primary text-white shadow'
                                    : 'text-gray-500 hover:text-gray-100'
                            "
                        >
                            Streets
                        </button>
                    </div>
                </div>
            </template>

            <!-- GUEST / CONSUMER SIDEBAR PANEL -->
            <template v-else>
                <!-- Intro / Tagline -->
                <div class="space-y-2">
                    <h2 class="text-base font-bold text-gray-100">
                        Map Local Businesses Across The Philippines
                    </h2>
                    <p class="text-xs leading-relaxed text-gray-500">
                        Explore real-time reviews, AI sentiments, and discover
                        unique local offerings via interactive geospatial
                        visualizer.
                    </p>
                </div>

                <!-- Sleek Input Concept Search -->
                <div class="relative">
                    <input
                        type="text"
                        placeholder="Search cafes, shops, hotels..."
                        class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-4 py-2.5 text-xs text-gray-100 placeholder-gray-500 transition-all duration-300 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                    />
                    <span class="absolute top-3 right-3.5 text-gray-500">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <circle cx="11" cy="11" r="8" />
                            <path d="M21 21l-4.35-4.35" />
                        </svg>
                    </span>
                </div>

                <!-- Category Chips Filter -->
                <div class="space-y-3">
                    <h3
                        class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                    >
                        Filter by Category
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cat in categoriesList"
                            :key="cat.id"
                            @click="filterCategory(cat.id)"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                            :class="[
                                selectedCategory === cat.id
                                    ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                    : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                            ]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-3 w-3"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path :d="cat.icon" />
                            </svg>
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Map Layer Switcher inside Sidebar -->
                <div class="space-y-3 border-t border-gray-500/20 pt-4">
                    <h3
                        class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                    >
                        Map View Mode
                    </h3>
                    <div
                        class="grid grid-cols-2 gap-2 rounded-xl border border-gray-500/20 bg-gray-900/30 p-1"
                    >
                        <button
                            @click="toggleLayer('satellite')"
                            class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                            :class="
                                activeLayer === 'satellite'
                                    ? 'bg-primary text-white shadow'
                                    : 'text-gray-500 hover:text-gray-100'
                            "
                        >
                            Satellite
                        </button>
                        <button
                            @click="toggleLayer('street')"
                            class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                            :class="
                                activeLayer === 'street'
                                    ? 'bg-primary text-white shadow'
                                    : 'text-gray-500 hover:text-gray-100'
                            "
                        >
                            Streets
                        </button>
                    </div>
                </div>

                <!-- Stats/Info panel -->
                <div
                    class="mt-auto grid grid-cols-2 gap-4 border-t border-gray-500/20 pt-4"
                >
                    <div>
                        <span class="block text-xs font-extrabold text-gray-100"
                            >12,879+</span
                        >
                        <span
                            class="text-[9px] font-semibold text-gray-500 uppercase"
                            >Active listings</span
                        >
                    </div>
                    <div>
                        <span class="block text-xs font-extrabold text-gray-100"
                            >48,290+</span
                        >
                        <span
                            class="text-[9px] font-semibold text-gray-500 uppercase"
                            >AI review outputs</span
                        >
                    </div>
                </div>

                <!-- Auth Buttons inside Sidebar Footer -->
                <nav
                    class="flex flex-col gap-2 border-t border-gray-500/20 pt-4"
                >
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="dashboard()"
                        class="w-full cursor-pointer rounded-xl bg-primary py-2.5 text-center text-xs font-bold text-white shadow-lg shadow-primary/10 transition-all hover:bg-primary-light"
                    >
                        Access Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="w-full cursor-pointer rounded-xl border border-gray-500/30 bg-gray-900 py-2.5 text-center text-xs font-bold text-gray-100 transition-all hover:bg-gray-900/70 hover:text-white"
                        >
                            Sign In
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="w-full cursor-pointer rounded-xl bg-gray-100 py-2.5 text-center text-xs font-bold text-gray-900 shadow-md shadow-gray-100/5 transition-all hover:bg-white"
                        >
                            Register Business
                        </Link>
                    </template>
                </nav>
            </template>
        </aside>

        <!-- STUNNING GLASSMORPHIC CREATION MODAL POPUP -->
        <div
            v-if="isCreateModalOpen"
            class="animate-fade-in absolute inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-500/30 bg-gray-900/90 font-sans shadow-2xl backdrop-blur-xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b border-gray-500/10 px-6 py-4"
                >
                    <div>
                        <h3
                            class="text-sm font-extrabold tracking-wider text-white uppercase"
                        >
                            Register Business Directory
                        </h3>
                        <p class="mt-0.5 text-[10px] text-gray-500">
                            Complete registration details to place custom pins
                            on database mapping.
                        </p>
                    </div>
                    <button
                        @click="closeCreateModal"
                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-900 hover:text-white"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Modal Tabs -->
                <div
                    class="flex border-b border-gray-500/10 bg-gray-900/35 px-4 text-xs font-semibold"
                >
                    <button
                        v-for="tab in [
                            'general',
                            'details',
                            'schedule',
                            'position',
                        ]"
                        :key="tab"
                        @click="currentTab = tab"
                        class="border-b-2 px-4 py-3 capitalize transition"
                        :class="
                            currentTab === tab
                                ? 'border-primary text-primary-light'
                                : 'border-transparent text-gray-500 hover:text-gray-300'
                        "
                    >
                        {{ tab }}
                    </button>
                </div>

                <!-- Modal Form Content Container -->
                <form
                    @submit.prevent="submitBusinessForm"
                    class="flex flex-col overflow-y-auto p-6 text-xs"
                >
                    <!-- TAB 1: GENERAL INFO -->
                    <div v-show="currentTab === 'general'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Listing Name *</label
                                >
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="e.g. Manila Coffee Roasters"
                                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                                />
                                <span
                                    v-if="form.errors.name"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.name }}</span
                                >
                            </div>
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Category *</label
                                >
                                <select
                                    v-model="form.category_id"
                                    required
                                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                                >
                                    <option
                                        value=""
                                        disabled
                                        class="bg-gray-900 text-gray-500"
                                    >
                                        Select Category
                                    </option>
                                    <option
                                        v-for="cat in categoriesList"
                                        :key="cat.id"
                                        :value="cat.id"
                                        class="bg-gray-900 text-gray-200"
                                    >
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <span
                                    v-if="form.errors.category_id"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.category_id }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="font-bold text-gray-300"
                                >Description *</label
                            >
                            <textarea
                                v-model="form.description"
                                required
                                rows="4"
                                placeholder="Briefly describe your business offerings, services, specialty..."
                                class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            ></textarea>
                            <span
                                v-if="form.errors.description"
                                class="text-[10px] text-red-400"
                                >{{ form.errors.description }}</span
                            >
                        </div>

                        <div class="space-y-1.5">
                            <label class="font-bold text-gray-300"
                                >Street Address *</label
                            >
                            <input
                                v-model="form.address"
                                type="text"
                                required
                                placeholder="Complete building number, street name, city, province"
                                class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                            />
                            <span
                                v-if="form.errors.address"
                                class="text-[10px] text-red-400"
                                >{{ form.errors.address }}</span
                            >
                        </div>
                    </div>

                    <!-- TAB 2: DETAILS -->
                    <div v-show="currentTab === 'details'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Contact Number</label
                                >
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="+63 917 123 4567"
                                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                                />
                                <span
                                    v-if="form.errors.phone"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.phone }}</span
                                >
                            </div>
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Website Address</label
                                >
                                <input
                                    v-model="form.website"
                                    type="text"
                                    placeholder="https://example.com"
                                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                                />
                                <span
                                    v-if="form.errors.website"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.website }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-4 border-t border-gray-500/20 pt-4 sm:grid-cols-2"
                        >
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Cover Photo Banner * (Max 5MB)</label
                                >
                                <div
                                    class="flex items-center justify-center rounded-xl border border-dashed border-gray-500/35 bg-gray-900/20 p-4"
                                >
                                    <div
                                        class="space-y-1 text-center text-gray-500"
                                    >
                                        <svg
                                            class="mx-auto h-6 w-6"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <input
                                            type="file"
                                            required
                                            @change="handleCoverUpload"
                                            accept="image/*"
                                            class="cursor-pointer text-[10px] font-bold text-primary-light"
                                        />
                                        <span
                                            v-if="form.cover_photo"
                                            class="mt-1 block text-[10px] font-bold text-emerald-400"
                                            >✓ Ready:
                                            {{ form.cover_photo.name }}</span
                                        >
                                    </div>
                                </div>
                                <span
                                    v-if="form.errors.cover_photo"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.cover_photo }}</span
                                >
                            </div>
                            <div class="space-y-1.5">
                                <label class="font-bold text-gray-300"
                                    >Brand Logo (Max 2MB)</label
                                >
                                <div
                                    class="flex items-center justify-center rounded-xl border border-dashed border-gray-500/35 bg-gray-900/20 p-4"
                                >
                                    <div
                                        class="space-y-1 text-center text-gray-500"
                                    >
                                        <svg
                                            class="mx-auto h-6 w-6"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                        <input
                                            type="file"
                                            @change="handleLogoUpload"
                                            accept="image/*"
                                            class="cursor-pointer text-[10px] font-bold text-primary-light"
                                        />
                                        <span
                                            v-if="form.logo"
                                            class="mt-1 block text-[10px] font-bold text-emerald-400"
                                            >✓ Ready: {{ form.logo.name }}</span
                                        >
                                    </div>
                                </div>
                                <span
                                    v-if="form.errors.logo"
                                    class="text-[10px] text-red-400"
                                    >{{ form.errors.logo }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: SCHEDULE -->
                    <div v-show="currentTab === 'schedule'" class="space-y-3">
                        <p
                            class="mb-3 border-b border-gray-500/10 pb-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                        >
                            Set Operating Hours
                        </p>

                        <div
                            v-for="(dayData, dayName) in form.hours"
                            :key="dayName"
                            class="flex flex-col justify-between gap-3 border-b border-gray-500/5 pb-2.5 sm:flex-row sm:items-center"
                        >
                            <span
                                class="w-24 text-xs font-extrabold text-gray-300 capitalize"
                                >{{ dayName }}</span
                            >
                            <div class="flex flex-wrap items-center gap-4">
                                <label
                                    class="flex cursor-pointer items-center gap-1.5 font-bold text-gray-400"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="dayData.closed"
                                        class="rounded border-gray-500/30 bg-gray-900 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    Closed
                                </label>
                                <div
                                    v-show="!dayData.closed"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        v-model="dayData.open"
                                        type="time"
                                        class="rounded-lg border border-gray-500/20 bg-gray-900/40 px-2 py-1 text-gray-200 focus:outline-none"
                                    />
                                    <span class="font-medium text-gray-500"
                                        >to</span
                                    >
                                    <input
                                        v-model="dayData.close"
                                        type="time"
                                        class="rounded-lg border border-gray-500/20 bg-gray-900/40 px-2 py-1 text-gray-200 focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: POSITION -->
                    <div
                        v-show="currentTab === 'position'"
                        class="flex flex-col gap-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-500/10 pb-2"
                        >
                            <p
                                class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                            >
                                Geospatial Marker Pin Placement
                            </p>
                            <div
                                class="flex items-center gap-3 text-[11px] font-semibold"
                            >
                                <div>
                                    <span class="text-gray-500">Lat:</span>
                                    <span class="font-mono text-gray-200">{{
                                        form.lat
                                    }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Lng:</span>
                                    <span class="font-mono text-gray-200">{{
                                        form.lng
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] leading-relaxed text-gray-500">
                            Click on the map or drag the blue marker to
                            precisely specify the directory location
                            coordinates.
                        </p>

                        <!-- Mini Map Inside Modal -->
                        <div
                            ref="modalMapContainer"
                            class="z-0 h-[320px] w-full rounded-xl border border-gray-500/20"
                        ></div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div
                        class="mt-8 flex items-center justify-between border-t border-gray-500/15 bg-gray-900/40 pt-4"
                    >
                        <div class="flex items-center gap-2">
                            <button
                                v-show="currentTab !== 'general'"
                                type="button"
                                @click="
                                    currentTab =
                                        currentTab === 'details'
                                            ? 'general'
                                            : currentTab === 'schedule'
                                              ? 'details'
                                              : 'schedule'
                                "
                                class="rounded-xl border border-gray-500/30 bg-gray-900 px-4 py-2 font-bold text-gray-400 transition hover:text-white"
                            >
                                Back
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-show="currentTab !== 'position'"
                                type="button"
                                @click="
                                    currentTab =
                                        currentTab === 'general'
                                            ? 'details'
                                            : currentTab === 'details'
                                              ? 'schedule'
                                              : 'position'
                                "
                                class="rounded-xl bg-primary px-5 py-2 font-bold text-white shadow transition hover:bg-primary-light"
                            >
                                Next Step
                            </button>
                            <button
                                v-show="currentTab === 'position'"
                                type="submit"
                                :disabled="form.processing"
                                class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-primary px-6 py-2.5 font-bold text-white shadow transition hover:bg-primary-light active:scale-98 disabled:opacity-50"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="h-3.5 w-3.5 animate-spin text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                Register Pin Directory
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

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
