import { router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import {
    DEFAULT_CENTER,
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
    const radius = ref(5);

    const results = ref<SearchBusiness[] | null>(null);
    const loading = ref(false);
    const error = ref('');
    const lastSearch = ref<SearchState | null>(null);
    const moved = ref(false);

    const requests = latestRequest();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let restoring = false;

    const listings = computed(
        () =>
            results.value ??
            initial().filter(
                (b) =>
                    category.value === 'all' ||
                    b.category?.name === category.value,
            ),
    );

    async function search(state?: SearchState, updateUrl = true) {
        const target = state ?? {
            ...center(),
            keyword: keyword.value,
            category: category.value,
            radius: radius.value,
        };
        const request = requests.start();
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
        clearTimeout(timer);
        results.value = null;
        lastSearch.value = null;
        error.value = '';
        loading.value = false;
        moved.value = false;

        if (updateUrl) {
            router.get(
                window.location.pathname,
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['allBusinesses'],
                },
            );
        }
    }

    function restore(url: string) {
        // Suppress filter watchers while restoring a single history snapshot.
        restoring = true;
        const state = readSearch(url);
        keyword.value = state?.keyword ?? '';
        category.value = state?.category ?? 'all';
        radius.value = state?.radius ?? 5;

        if (state) {
            void search(state, false);
        } else {
            reset(false);
        }

        queueMicrotask(() => {
            restoring = false;
        });
    }

    // Filter changes rerun an active search without fetching during map drags.
    watch(
        category,
        () => {
            if (!restoring && (lastSearch.value || loading.value)) {
                void search({
                    ...(lastSearch.value ?? center()),
                    category: category.value,
                    radius: radius.value,
                    keyword: keyword.value,
                });
            }
        },
        { flush: 'sync' },
    );

    watch(
        radius,
        () => {
            clearTimeout(timer);

            if (!restoring && (lastSearch.value || loading.value)) {
                // Invalidate immediately so old results cannot win during the debounce.
                requests.cancel();
                timer = setTimeout(() => {
                    void search({
                        ...(lastSearch.value ?? center()),
                        radius: radius.value,
                        category: category.value,
                        keyword: keyword.value,
                    });
                }, 300);
            }
        },
        { flush: 'sync' },
    );

    onUnmounted(() => {
        requests.cancel();
        clearTimeout(timer);
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
        moved,
        search,
        reset,
        restore,
        initialCenter:
            readSearch(
                typeof window !== 'undefined' ? window.location.href : '/',
            ) ?? DEFAULT_CENTER,
    };
}
