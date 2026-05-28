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
}

export function useProximitySearch({
    categoriesList,
    listingsList,
    selectedCategory,
    renderMarkers,
    panToLocation,
    getMapCenter,
}: UseProximitySearchOptions) {
    const searchResults = ref<Business[]>([]);
    const isNearbySearchActive = ref(false);

    async function performSearch(
        latitude: number,
        longitude: number,
        radius: number,
        keyword: string,
    ) {
        try {
            const searchRoute = businesses.search({
                query: {
                    lat: latitude,
                    lng: longitude,
                    radius: radius,
                    ...(keyword ? { keyword } : {}),
                },
            });

            const response = await fetch(searchRoute.url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data?.status === 'success') {
                searchResults.value = data.data;
                isNearbySearchActive.value = true;

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

                // Pan to first result if available
                if (data.data.length > 0) {
                    panToLocation(data.data[0].lat, data.data[0].lng);
                }
            }
        } catch (error) {
            console.error('Geospatial proximity search failed:', error);
        }
    }

    function search({ radius, keyword }: { radius: number; keyword: string }) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    performSearch(
                        position.coords.latitude,
                        position.coords.longitude,
                        radius,
                        keyword,
                    );
                },
                () => {
                    const center = getMapCenter();

                    if (center) {
                        performSearch(center.lat, center.lng, radius, keyword);
                    }
                },
                { timeout: 5000 },
            );
        } else {
            const center = getMapCenter();

            if (center) {
                performSearch(center.lat, center.lng, radius, keyword);
            }
        }
    }

    function reset() {
        isNearbySearchActive.value = false;
        searchResults.value = [];

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
        search,
        reset,
    };
}
