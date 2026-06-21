import { ref } from 'vue';
import type { Ref } from 'vue';
import type { Category, ListingPin } from '@/types';

export function useLeafletMap(mapContainer: Ref<HTMLDivElement | null>) {
    let L: any = null;
    let map: any = null;
    let markerLayerGroup: any = null;
    let proximityCircle: any = null;
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
                crossOrigin: true,
            },
        );
        backgroundLayer.addTo(map);

        const satelliteLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxZoom: 18,
                crossOrigin: true,
                attribution:
                    'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            },
        );

        const labelsLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
            { maxZoom: 18, crossOrigin: true },
        );

        const streetLayer = L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            {
                maxZoom: 18,
                crossOrigin: true,
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
                TruckIcon:
                    'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25m-2.25 0h-2.25m0 0v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677',
                AcademicCapIcon:
                    'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
                MusicalNoteIcon:
                    'M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V4.846a2.25 2.25 0 0 0-1.632-2.163l-1.32-.377a1.803 1.803 0 1 0-.99 3.467l2.31.66A2.25 2.25 0 0 1 9 9Z',
                ShoppingCartIcon:
                    'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
                ScissorsIcon:
                    'M7.848 8.25l1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.33 4.33 0 0 0 10.607 12m3.736 0 7.794 4.5-.804.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664',
                BuildingOffice2Icon:
                    'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
                PaintBrushIcon:
                    'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42',
                GlobeAltIcon:
                    'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
                WifiIcon:
                    'M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z',
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

    function panToLocation(lat: number, lng: number, zoom: number = 15) {
        if (map) {
            map.setView([lat, lng], zoom, { animate: true, duration: 1.5 });
        }
    }

    function showUserLocation(lat: number, lng: number) {
        if (!L || !map) {
            return;
        }

        const locationHtml = `
            <div class="relative flex items-center justify-center" style="width: 60px; height: 60px;">
                <span class="absolute inline-flex h-14 w-14 animate-ping rounded-full bg-cyan-400 opacity-25"></span>
                <span class="absolute inline-flex h-10 w-10 rounded-full bg-cyan-400/20 border border-cyan-400/40"></span>
                <div class="relative flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-br from-cyan-400 to-blue-600 border-[3px] border-white shadow-[0_0_15px_rgba(34,211,238,0.6)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
        `;

        const userIcon = L.divIcon({
            html: locationHtml,
            className: 'user-location-icon',
            iconSize: [60, 60],
            iconAnchor: [30, 30],
        });

        const marker = L.marker([lat, lng], {
            icon: userIcon,
            interactive: true,
        }).addTo(map);
        marker.bindPopup(
            `<div class="p-2 text-center font-sans">
                <p class="text-xs font-bold text-zinc-800">📍 You are here</p>
            </div>`,
            { closeButton: false, className: 'custom-leaflet-popup' },
        );
    }

    function getMapCenter() {
        return map ? map.getCenter() : null;
    }

    function showProximityCircle(lat: number, lng: number, radiusKm: number) {
        console.log('[useLeafletMap] showProximityCircle called:', {
            lat,
            lng,
            radiusKm,
            L: !!L,
            map: !!map,
        });

        if (!L || !map) {
            console.warn('[useLeafletMap] Leaflet or map not initialized');

            return;
        }

        if (proximityCircle) {
            console.log('[useLeafletMap] Removing existing circle');
            map.removeLayer(proximityCircle);
        }

        try {
            proximityCircle = L.circle([lat, lng], {
                radius: radiusKm * 1000, // Leaflet expects meters
                color: '#6366f1', // Sleek indigo stroke
                weight: 1.5,
                opacity: 0.8,
                fillColor: '#818cf8', // Translucent indigo fill
                fillOpacity: 0.15,
                dashArray: '4, 4', // Dashed border
                interactive: false,
            }).addTo(map);

            console.log(
                '[useLeafletMap] Circle added to map:',
                proximityCircle,
            );

            // Zoom map to fit the circle bounds so the user can see the full range
            const bounds = proximityCircle.getBounds();
            console.log('[useLeafletMap] Fitting bounds:', bounds);
            map.fitBounds(bounds, {
                padding: [20, 20],
                animate: true,
                duration: 1.5,
            });
        } catch (err) {
            console.error(
                '[useLeafletMap] Error creating or adding circle:',
                err,
            );
        }
    }

    function clearProximityCircle() {
        if (map && proximityCircle) {
            map.removeLayer(proximityCircle);
            proximityCircle = null;
        }
    }

    return {
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
    };
}
