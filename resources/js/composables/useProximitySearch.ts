import { ref } from 'vue';
import type { Ref } from 'vue';
import businesses from '@/routes/businesses';
import type { Business, Category, ListingPin } from '@/types';

interface UseProximitySearchOptions {
    categoriesList: Ref<Category[]>;
    listingsList: Ref<Business[]>;
    selectedCategory: Ref<string | number>;
    renderMarkers: (
        categories: Category[],
        listings: ListingPin[],
        selectedCategory: Ref<string | number>,
    ) => void;
    panToLocation: (lat: number, lng: number, zoom?: number) => void;
    getMapCenter: () => { lat: number; lng: number } | null;
    showProximityCircle: (lat: number, lng: number, radiusKm: number) => void;
    clearProximityCircle: () => void;
}

export function useProximitySearch({
    categoriesList,
    listingsList,
    selectedCategory,
    renderMarkers,
    panToLocation,
    getMapCenter,
    showProximityCircle,
    clearProximityCircle,
}: UseProximitySearchOptions) {
    const searchResults = ref<Business[]>([]);
    const isNearbySearchActive = ref(false);
    const lastSearchCenter = ref<{ lat: number; lng: number } | null>(null);

    async function performSearch(
        latitude: number,
        longitude: number,
        radius: number,
        keyword: string,
    ) {
        console.log('[useProximitySearch] performSearch called:', {
            latitude,
            longitude,
            radius,
            keyword,
        });

        try {
            const searchRoute = businesses.search({
                query: {
                    lat: latitude,
                    lng: longitude,
                    radius: radius,
                    ...(keyword ? { keyword } : {}),
                },
            });

            console.log(
                '[useProximitySearch] Fetching from URL:',
                searchRoute.url,
            );
            const response = await fetch(searchRoute.url, {
                headers: { Accept: 'application/json' },
            });

            console.log(
                '[useProximitySearch] Fetch response status:',
                response.status,
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('[useProximitySearch] Fetch response data:', data);

            if (data?.status === 'success') {
                searchResults.value = data.data;
                isNearbySearchActive.value = true;
                lastSearchCenter.value = { lat: latitude, lng: longitude };

                // Update URL with search params
                const params = new URLSearchParams();

                if (!keyword) {
                    params.set('lat', latitude.toString());
                    params.set('lng', longitude.toString());
                }

                params.set('radius', radius.toString());

                if (keyword) {
                    params.set('keyword', keyword);
                }

                window.history.replaceState(
                    {},
                    '',
                    `${window.location.pathname}?${params.toString()}`,
                );

                // Redraw nearby pins on map
                renderMarkers(
                    categoriesList.value,
                    searchResults.value as ListingPin[],
                    ref('all'),
                );

                // Draw the proximity circle around the search center
                showProximityCircle(latitude, longitude, radius);

                // Pan to first result if available, otherwise pan to search center
                if (data.data.length > 0) {
                    panToLocation(data.data[0].lat, data.data[0].lng);
                } else {
                    panToLocation(latitude, longitude);
                }
            }
        } catch (error) {
            console.error('Geospatial proximity search failed:', error);
        }
    }

    function search({ radius, keyword }: { radius: number; keyword: string }) {
        console.log('[useProximitySearch] search triggered with:', {
            radius,
            keyword,
            hasGeolocation: !!navigator.geolocation,
        });

        // If search is already active, use the last search center immediately
        if (isNearbySearchActive.value && lastSearchCenter.value) {
            console.log(
                '[useProximitySearch] Search is active, using last search center:',
                lastSearchCenter.value,
            );
            performSearch(
                lastSearchCenter.value.lat,
                lastSearchCenter.value.lng,
                radius,
                keyword,
            );

            return;
        }

        if (navigator.geolocation) {
            console.log(
                '[useProximitySearch] Requesting geolocation current position...',
            );
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    console.log('[useProximitySearch] Geolocation success:', {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    });
                    performSearch(
                        position.coords.latitude,
                        position.coords.longitude,
                        radius,
                        keyword,
                    );
                },
                (err) => {
                    console.warn(
                        '[useProximitySearch] Geolocation error/timeout:',
                        err,
                    );
                    const center = getMapCenter();
                    console.log(
                        '[useProximitySearch] Using map center:',
                        center,
                    );

                    if (center) {
                        performSearch(center.lat, center.lng, radius, keyword);
                    }
                },
                { timeout: 5000 },
            );
        } else {
            console.log(
                '[useProximitySearch] Geolocation not supported by browser',
            );
            const center = getMapCenter();
            console.log('[useProximitySearch] Using map center:', center);

            if (center) {
                performSearch(center.lat, center.lng, radius, keyword);
            }
        }
    }

    function reset() {
        isNearbySearchActive.value = false;
        searchResults.value = [];
        lastSearchCenter.value = null;

        // Clear proximity circle from map
        clearProximityCircle();

        // Clear search params from URL
        window.history.replaceState({}, '', window.location.pathname);

        // Restore original business listing pins
        renderMarkers(
            categoriesList.value,
            listingsList.value as ListingPin[],
            selectedCategory,
        );
    }

    return {
        searchResults,
        isNearbySearchActive,
        lastSearchCenter,
        search,
        reset,
    };
}
