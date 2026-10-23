import { router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { DEFAULT_CENTER, DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import type {
    BusinessMarker,
    Coordinates,
    SearchBusiness,
    SearchPagination,
    SearchState,
} from '@/types';
import { latestRequest } from '@/utils/requests';
import { readSearch, searchParams } from '@/utils/search';

export function useExplorerSearch(
    center: () => Coordinates,
    userLocation: () => Coordinates | null = () => null,
) {
    const keyword = ref('');
    const category = ref('all');
    const shortcut = ref('');
    const radius = ref(DEFAULT_SEARCH_RADIUS);
    const listings = ref<SearchBusiness[]>([]);
    const markers = ref<BusinessMarker[]>([]);
    const pagination = ref<SearchPagination>({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });
    const loading = ref(false);
    const error = ref('');
    const initialSearch = readSearch(
        typeof window !== 'undefined' ? window.location.href : '/',
    );
    const lastSearch = ref<SearchState | null>(null);
    const searchArea = ref<SearchState | null>(initialSearch);
    const moved = ref(false);
    const requests = latestRequest();
    const selections = latestRequest();
    const selecting = ref(false);
    let initialized = false;
    let requestedPage = 1;
    let pendingState: SearchState | null = null;
    let pendingCleared = false;

    const visibleSearchArea = computed<SearchState>(
        () =>
            searchArea.value ?? {
                ...(userLocation() ?? initialSearch ?? DEFAULT_CENTER),
                radius: DEFAULT_SEARCH_RADIUS,
                category: 'all',
                keyword: '',
            },
    );

    async function search(
        state?: SearchState,
        updateUrl = true,
        page = 1,
        cleared = false,
    ) {
        const target = state ?? {
            ...center(),
            keyword: keyword.value,
            category: category.value,
            shortcut: shortcut.value || undefined,
            radius: radius.value,
        };
        const includeMarkers =
            page === 1 ||
            !lastSearch.value ||
            searchParams(target).toString() !==
                searchParams(lastSearch.value).toString();
        const request = requests.start();
        selections.cancel();
        selecting.value = false;
        initialized = true;
        requestedPage = page;
        pendingState = target;
        pendingCleared = cleared;
        searchArea.value = cleared ? null : target;
        loading.value = true;
        error.value = '';

        if (includeMarkers) {
            listings.value = [];
            markers.value = [];
            pagination.value = {
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
            };
        }

        try {
            const query = searchParams(target, page);
            query.set('include_markers', includeMarkers ? '1' : '0');
            const response = await fetch(`/businesses/search?${query}`, {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Search failed');
            }

            const json = (await response.json()) as {
                status: string;
                data: SearchBusiness[];
                markers?: BusinessMarker[];
                meta: SearchPagination;
            };

            if (!request.isCurrent()) {
                return;
            }

            if (
                json.status !== 'success' ||
                !Array.isArray(json.data) ||
                !json.meta ||
                (includeMarkers && !Array.isArray(json.markers))
            ) {
                throw new Error('Invalid search response');
            }

            listings.value = json.data;
            pagination.value = json.meta;
            requestedPage = json.meta.current_page;

            if (json.markers) {
                markers.value = json.markers;
            }

            lastSearch.value = cleared ? null : target;
            moved.value = false;

            loading.value = false;

            if (updateUrl) {
                router.push({
                    url: `${window.location.pathname}?${searchParams(target, json.meta.current_page)}`,
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        } catch {
            if (request.isCurrent()) {
                error.value =
                    'We could not load nearby places. Try searching again.';
            }
        } finally {
            if (request.isCurrent()) {
                loading.value = false;
            }
        }
    }

    function clearShortcut() {
        shortcut.value = '';
        const applied = searchArea.value;

        if (!applied?.shortcut) {
            return;
        }

        // Remove only the applied shortcut; other draft filters still wait for Search.
        const target = { ...applied, shortcut: undefined };
        void search(target, false);
        router.replace({
            url: `${window.location.pathname}?${searchParams(target)}`,
            preserveScroll: true,
            preserveState: true,
        });
    }

    function reset(updateUrl = true) {
        keyword.value = '';
        category.value = 'all';
        shortcut.value = '';
        radius.value = DEFAULT_SEARCH_RADIUS;
        lastSearch.value = null;
        searchArea.value = null;
        moved.value = false;
        void search(visibleSearchArea.value, false, 1, true);

        if (updateUrl) {
            router.push({
                url: window.location.pathname,
                preserveState: true,
                preserveScroll: true,
            });
        }
    }

    function restore(url: string) {
        const state = readSearch(url);
        const requested = Number(
            new URL(url, 'http://localhost').searchParams.get('page') ?? 1,
        );
        const page =
            Number.isInteger(requested) && requested > 0 && requested <= 100000
                ? requested
                : 1;
        const sameSearch =
            state &&
            lastSearch.value &&
            searchParams(state).toString() ===
                searchParams(lastSearch.value).toString();

        if (
            loading.value &&
            ((state &&
                pendingState &&
                !pendingCleared &&
                searchParams(state).toString() ===
                    searchParams(pendingState).toString() &&
                page === requestedPage) ||
                (!state && pendingCleared))
        ) {
            return;
        }

        // Inertia emits navigate for our own URL updates too.
        if (
            initialized &&
            !loading.value &&
            ((sameSearch && page === pagination.value.current_page) ||
                (!state && !lastSearch.value))
        ) {
            return;
        }

        keyword.value = state?.keyword ?? '';
        category.value = state?.category ?? 'all';
        shortcut.value = state?.shortcut ?? '';
        radius.value = state?.radius ?? DEFAULT_SEARCH_RADIUS;

        if (state) {
            void search(state, false, page);
        } else {
            reset(false);
        }
    }

    function changePage(page: number) {
        if (loading.value || page < 1 || page > pagination.value.last_page) {
            return;
        }

        void search(searchArea.value ?? visibleSearchArea.value, true, page);
    }

    function retry() {
        void search(
            searchArea.value ?? visibleSearchArea.value,
            !!searchArea.value,
            requestedPage,
            !searchArea.value,
        );
    }

    // Stop a nearby search before switching to a shared collection.
    function cancelSearch() {
        requests.cancel();
        cancelSelection();
        loading.value = false;
        pendingState = null;
    }

    function cancelSelection() {
        selections.cancel();
        selecting.value = false;
    }

    async function findBusiness(id: number): Promise<SearchBusiness | null> {
        const request = selections.start();
        selecting.value = false;
        const listed = listings.value.find((place) => place.id === id);

        if (listed) {
            return listed;
        }

        const pin = markers.value.find((place) => place.id === id);

        if (!pin) {
            return null;
        }

        selecting.value = true;

        try {
            const response = await fetch(
                `/businesses/${encodeURIComponent(pin.slug)}`,
                {
                    signal: request.signal,
                    headers: { Accept: 'application/json' },
                },
            );

            if (!response.ok) {
                throw new Error('Place unavailable');
            }

            const json = (await response.json()) as { data: SearchBusiness };

            return request.isCurrent() ? json.data : null;
        } catch {
            if (request.isCurrent()) {
                error.value =
                    'This place could not be loaded. Select it again or search again.';
            }

            return null;
        } finally {
            if (request.isCurrent()) {
                selecting.value = false;
            }
        }
    }

    onUnmounted(() => {
        requests.cancel();
        selections.cancel();
    });

    return {
        keyword,
        category,
        shortcut,
        radius,
        listings,
        markers,
        pagination,
        loading,
        error,
        lastSearch,
        searchArea,
        visibleSearchArea,
        moved,
        search,
        reset,
        clearShortcut,
        restore,
        changePage,
        retry,
        findBusiness,
        cancelSelection,
        cancelSearch,
        selecting,
        initialCenter: initialSearch ?? DEFAULT_CENTER,
    };
}
