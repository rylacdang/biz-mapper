import { router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import {
    DEFAULT_CENTER,
    DEFAULT_SEARCH_RADIUS,
    distanceInKilometers,
    latestRequest,
    readSearch,
    searchParams,
} from '@/lib/explorer';
import type { Coordinates, SearchBusiness, SearchState } from '@/types';

export function useExplorerSearch(
    initial: () => SearchBusiness[],
    center: () => Coordinates,
) {
    // Filters are shared by the toolbar, explorer panel, and URL state.
    const keyword = ref('');
    const category = ref('all');
    const radius = ref(DEFAULT_SEARCH_RADIUS);

    const results = ref<SearchBusiness[] | null>(null);
    const loading = ref(false);
    const error = ref('');
    const initialSearch = readSearch(
        typeof window !== 'undefined' ? window.location.href : '/',
    );
    const lastSearch = ref<SearchState | null>(null);
    const searchArea = ref<SearchState | null>(initialSearch);
    const moved = ref(false);

    const requests = latestRequest();
    const visibleSearchArea = computed<SearchState>(
        () =>
            searchArea.value ?? {
                ...center(),
                radius: DEFAULT_SEARCH_RADIUS,
                category: 'all',
                keyword: '',
            },
    );

    const listings = computed(() => {
        const area = visibleSearchArea.value;

        // Clearing filters still limits discovery to the circle shown on the map.
        return (searchArea.value ? (results.value ?? []) : initial())
            .map((place) => ({
                ...place,
                distance: distanceInKilometers(area, place),
            }))
            .filter(
                (place) =>
                    Number.isFinite(place.distance) &&
                    place.distance <= area.radius &&
                    (area.category === 'all' ||
                        place.category?.name === area.category ||
                        String(place.category?.id) === area.category),
            )
            .sort((a, b) => a.distance - b.distance || a.id - b.id);
    });

    async function search(state?: SearchState, updateUrl = true) {
        const target = state ?? {
            ...center(),
            keyword: keyword.value,
            category: category.value,
            radius: radius.value,
        };
        const request = requests.start();
        // The radius is known before the listings response arrives.
        searchArea.value = target;
        results.value = [];
        loading.value = true;
        error.value = '';

        try {
            const response = await fetch(
                `/businesses/search?${searchParams(target)}`,
                {
                    signal: request.signal,
                    headers: { Accept: 'application/json' },
                },
            );

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const json = (await response.json()) as {
                status: string;
                data: SearchBusiness[];
            };

            // Aborting alone cannot reject a response that has already resolved.
            if (!request.isCurrent()) {
                return;
            }

            if (json.status !== 'success' || !Array.isArray(json.data)) {
                throw new Error('Invalid search response');
            }

            results.value = json.data;
            lastSearch.value = target;
            moved.value = false;

            if (updateUrl) {
                router.push({
                    url: `${window.location.pathname}?${searchParams(target)}`,
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        } catch {
            if (request.isCurrent()) {
                error.value =
                    'We couldn’t load nearby places. Try searching again.';
            }
        } finally {
            if (request.isCurrent()) {
                loading.value = false;
            }
        }
    }

    function reset(updateUrl = true) {
        requests.cancel();
        keyword.value = '';
        category.value = 'all';
        radius.value = DEFAULT_SEARCH_RADIUS;
        results.value = null;
        lastSearch.value = null;
        searchArea.value = null;
        error.value = '';
        loading.value = false;
        moved.value = false;

        if (updateUrl) {
            // Clearing a local search should not recreate the map through a page visit.
            router.push({
                url: window.location.pathname,
                preserveState: true,
                preserveScroll: true,
            });
        }
    }

    function restore(url: string) {
        const state = readSearch(url);
        keyword.value = state?.keyword ?? '';
        category.value = state?.category ?? 'all';
        radius.value = state?.radius ?? DEFAULT_SEARCH_RADIUS;

        if (state) {
            void search(state, false);
        } else {
            reset(false);
        }
    }

    onUnmounted(() => {
        requests.cancel();
    });

    return {
        keyword,
        category,
        radius,
        listings,
        results,
        loading,
        error,
        lastSearch,
        searchArea,
        visibleSearchArea,
        moved,
        search,
        reset,
        restore,
        initialCenter: initialSearch ?? DEFAULT_CENTER,
    };
}
