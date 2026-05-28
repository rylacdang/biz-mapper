import { ref } from 'vue';
import type { Ref } from 'vue';
import type { Category, ListingPin } from '@/types';

export function useLeafletMap(mapContainer: Ref<HTMLDivElement | null>) {
    let L: any = null;
    let map: any = null;
    let markerLayerGroup: any = null;
    const isMapLoaded = ref(false);
    const activeLayer = ref('satellite');

    async function initMap(
        categories: Category[],
        listings: ListingPin[],
        selectedCategory: Ref<string | number>,
    ) {
        if (map || !mapContainer.value) {
            return;
        }

        // SSR safe dynamic load
        const LeafletModule = await import('leaflet');
        L = LeafletModule.default || LeafletModule;
        await import('leaflet/dist/leaflet.css');

        map = L.map(mapContainer.value, {
            center: [12.8797, 121.774],
            zoom: 6,
            minZoom: 5,
            maxZoom: 18,
            zoomControl: false,
        });

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // Low-res background layer — loads fast, covers entire view,
        // eliminates white gaps while high-res tiles load on top
        const backgroundLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxNativeZoom: 5,
                maxZoom: 18,
                minZoom: 5,
            },
        );
        backgroundLayer.addTo(map);

        const satelliteLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxZoom: 18,
                attribution:
                    'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            },
        );

        const labelsLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
            { maxZoom: 18 },
        );

        const streetLayer = L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            },
        );

        const hybridGroup = L.layerGroup([satelliteLayer, labelsLayer]);
        hybridGroup.addTo(map);

        (window as any).mapLayers = {
            satellite: hybridGroup,
            street: streetLayer,
            background: backgroundLayer,
        };

        markerLayerGroup = L.layerGroup().addTo(map);
        renderMarkers(categories, listings, selectedCategory);
        isMapLoaded.value = true;
    }

    function renderMarkers(
        categories: Category[],
        listings: ListingPin[],
        selectedCategory: Ref<string | number>,
    ) {
        if (!L || !map || !markerLayerGroup) {
            return;
        }

        markerLayerGroup.clearLayers();

        listings.forEach((listing) => {
            const listingCategory = listing.category_id || listing.category;

            if (listingCategory === undefined || listingCategory === null) {
                return;
            }

            // Find matching category object first
            const categoryObj = categories.find((c) => {
                if (typeof listingCategory === 'object') {
                    return (
                        c.id === (listingCategory as any).id ||
                        c.name === (listingCategory as any).name
                    );
                }

                return (
                    c.id === listingCategory ||
                    c.id.toString() === listingCategory.toString()
                );
            });

            if (!categoryObj) {
                return;
            }

            // Filter markers by category name rather than ID
            if (
                selectedCategory.value !== 'all' &&
                categoryObj.name !== selectedCategory.value
            ) {
                return;
            }

            const pinColor = categoryObj
                ? categoryObj.color
                : listing.color || '#185FA5';

            // Map Heroicon component names to SVG path strings for Leaflet rendering
            const iconPaths: Record<string, string> = {
                BuildingStorefrontIcon:
                    'M2.25 21h19.5m-18-10.5h16.5M2.25 9h19.5M4.5 9v12m15-12v12M6.75 21V13.5a2.25 2.25 0 0 1 4.5 0v.75m0-.75a2.25 2.25 0 0 1 4.5 0v7.5',
                FireIcon:
                    'M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z',
                SparklesIcon:
                    'M9.813 15.904L9 21l-.813-5.096L3 15l5.096-.813L9 9l.813 5.096L15 15l-5.188.904zm8.438-9.404L18 9l-.25-2.5L15 6l2.5-.25L18 3l.25 2.5L21 6l-2.75.5z',
                CakeIcon:
                    'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                ShoppingBagIcon:
                    'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0z',
                WrenchScrewdriverIcon:
                    'M21.75 6.75a4.5 4.5 0 0 1-4.884 4.484c-1.076-.091-2.264.071-2.95.757l-6.417 6.417a2.25 2.25 0 0 1-3.182-3.182l6.417-6.417c.686-.686.848-1.874.757-2.95A4.5 4.5 0 0 1 16 2.25M6 21.75 2.25 18',
                HeartIcon:
                    'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
                HomeIcon:
                    'M2.25 12 12 3l9.75 9M18.75 19.5v-7.5M5.25 19.5v-7.5M12 19.5V12m-9 7.5h18',
                FaceSmileIcon:
                    'M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-2.625 6c.746 0 1.35.604 1.35 1.35s-.604 1.35-1.35 1.35-1.35-.604-1.35-1.35.604-1.35 1.35-1.35zm5.25 0c.746 0 1.35.604 1.35 1.35s-.604 1.35-1.35 1.35-1.35-.604-1.35-1.35.604-1.35 1.35-1.35zm-5.25 6.75c.9-.15 1.762-.3 2.625-.3s1.725.15 2.625.3c-.3 1.8-1.5 3.45-2.625 3.45s-2.325-1.65-2.625-3.45z',
                ComputerDesktopIcon:
                    'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25a2.25 2.25 0 0 1-2.25-2.25V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
                BanknotesIcon:
                    'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5M5.25 7.5h13.5m-15 9h16.5M5.25 15h13.5m-15-6h16.5M5.25 12h13.5',
            };

            const iconPath =
                categoryObj && iconPaths[categoryObj.icon]
                    ? iconPaths[categoryObj.icon]
                    : categoryObj?.icon ||
                      'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z';

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

            const ratingVal = listing.rating || 5.0;
            const reviewsCountVal =
                listing.reviewsCount !== undefined ? listing.reviewsCount : 0;
            const starsHtml =
                '★'.repeat(Math.round(ratingVal)) +
                '☆'.repeat(5 - Math.round(ratingVal));

            const popupContent = `
                <div class="p-3.5 max-w-[250px] font-sans">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <span class="inline-block px-2 py-0.5 text-[9px] font-bold tracking-wider text-white uppercase rounded-md shadow-sm" style="background-color: ${pinColor};">
                            ${categoryObj ? categoryObj.name : listingCategory}
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
    }

    function toggleLayer(layer: string) {
        if (!map || !(window as any).mapLayers) {
            return;
        }

        activeLayer.value = layer;
        const layers = (window as any).mapLayers;

        if (layer === 'satellite') {
            map.removeLayer(layers.street);
            layers.background.addTo(map);
            layers.satellite.addTo(map);
        } else {
            map.removeLayer(layers.satellite);
            map.removeLayer(layers.background);
            layers.street.addTo(map);
        }
    }

    function panToLocation(lat: number, lng: number) {
        if (map) {
            map.setView([lat, lng], 15, { animate: true, duration: 1.5 });
        }
    }

    function getMapCenter() {
        return map ? map.getCenter() : null;
    }

    return {
        isMapLoaded,
        activeLayer,
        initMap,
        renderMarkers,
        toggleLayer,
        panToLocation,
        getMapCenter,
    };
}
