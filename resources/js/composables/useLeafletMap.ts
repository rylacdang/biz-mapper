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
            zoomControl: false,
        });

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        const satelliteLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                attribution:
                    'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            },
        );

        const labelsLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
        );

        const streetLayer = L.tileLayer(
            'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
            },
        );

        const hybridGroup = L.layerGroup([satelliteLayer, labelsLayer]);
        hybridGroup.addTo(map);

        (window as any).mapLayers = {
            satellite: hybridGroup,
            street: streetLayer,
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

            if (
                selectedCategory.value !== 'all' &&
                listingCategory !== selectedCategory.value
            ) {
                return;
            }

            const categoryObj = categories.find(
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
            layers.satellite.addTo(map);
        } else {
            map.removeLayer(layers.satellite);
            layers.street.addTo(map);
        }
    }

    function panToLocation(lat: number, lng: number) {
        if (map) {
            map.setView([lat, lng], 15, { animate: true, duration: 1.5 });
        }
    }

    return {
        isMapLoaded,
        activeLayer,
        initMap,
        renderMarkers,
        toggleLayer,
        panToLocation,
    };
}
