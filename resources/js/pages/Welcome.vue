<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { onMounted, ref } from 'vue';

defineProps<{
    canRegister: boolean;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
const isMapLoaded = ref(false);
const selectedCategory = ref('all');
const activeLayer = ref('satellite');

// Category options
const categories = [
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
const listings = [
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

let L: any = null;
let map: any = null;
let markerLayerGroup: any = null;

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

    listings.forEach((listing) => {
        if (
            selectedCategory.value !== 'all' &&
            listing.category !== selectedCategory.value
        ) {
            return;
        }

        // Beautiful custom glowing HTML map pins matching category color
        const iconHtml = `
            <div class="relative flex items-center justify-center cursor-pointer" style="width: 36px; height: 36px;">
                <span class="absolute inline-flex h-9 w-9 animate-ping rounded-full opacity-35" style="background-color: ${listing.color};"></span>
                <div class="relative flex items-center justify-center h-7 w-7 rounded-full border-2 border-white shadow-xl text-white transform hover:scale-110 transition-transform duration-200" style="background-color: ${listing.color};">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        ${
                            listing.category === 'food'
                                ? '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>'
                                : listing.category === 'retail'
                                  ? '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>'
                                  : listing.category === 'accommodation'
                                    ? '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'
                                    : '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'
                        }
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
        const starsHtml =
            '★'.repeat(Math.round(listing.rating)) +
            '☆'.repeat(5 - Math.round(listing.rating));
        const popupContent = `
            <div class="p-3.5 max-w-[250px] font-sans">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="inline-block px-2 py-0.5 text-[9px] font-bold tracking-wider text-white uppercase rounded-md shadow-sm" style="background-color: ${listing.color};">
                        ${listing.category}
                    </span>
                    <span class="text-[10px] text-zinc-400 font-medium">Philippines</span>
                </div>
                <h4 class="m-0 text-sm font-bold text-zinc-900 dark:text-zinc-900 leading-snug">${listing.name}</h4>
                <div class="flex items-center gap-1 my-1 text-xs text-amber-500">
                    <span class="font-bold text-zinc-800">${listing.rating}</span>
                    <span>${starsHtml}</span>
                    <span class="text-zinc-400 font-normal">(${listing.reviewsCount})</span>
                </div>
                <p class="m-0 text-xs text-zinc-600 dark:text-zinc-600 leading-relaxed font-normal">${listing.description}</p>
                <div class="mt-3 pt-2.5 border-t border-zinc-100 flex items-center justify-end">
                    <a href="/login" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider flex items-center gap-0.5">
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

const filterCategory = (categoryId: string) => {
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
                        Hyperlocal Discovery
                    </p>
                </div>
            </div>

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
                        v-for="cat in categories"
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
            <nav class="flex flex-col gap-2 border-t border-gray-500/20 pt-4">
                <Link
                    v-if="$page.props.auth.user"
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
        </aside>

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
