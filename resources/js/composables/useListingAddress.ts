import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { searchAddresses } from '@/lib/addressSearch';
import { latestRequest } from '@/lib/explorer';
import { matchingPlaces, normalizePlaceName } from '@/lib/psgcMatching';
import type { AddressSuggestion, PsgcItem } from '@/types';

interface AddressFields {
    address: string;
    region_code: string;
    province_code: string;
    city_code: string;
    barangay_code: string;
    lat: number;
    lng: number;
}

export function useListingAddress(form: AddressFields) {
    // PSGC choices are kept separate from the listing's saved address fields.
    const regions = ref<PsgcItem[]>([]);
    const provinces = ref<PsgcItem[]>([]);
    const cities = ref<PsgcItem[]>([]);
    const barangays = ref<PsgcItem[]>([]);

    const loading = ref(false);
    const error = ref('');
    const locating = ref(false);
    const suggestions = ref<AddressSuggestion[]>([]);
    const suggestionError = ref('');
    const searched = ref(false);
    const matchingLocation = ref(false);

    const regionRequest = latestRequest();
    const cityRequest = latestRequest();
    const geocodeRequest = latestRequest();
    const controllers = new Set<AbortController>();
    const selectionRequest = latestRequest();
    let initializing = true;
    let autofilling = false;
    let initialization: Promise<void> | undefined;
    let alive = true;
    let suggestionTimer: ReturnType<typeof setTimeout> | undefined;
    let selectedAddress = '';
    const suggestionCache = new Map<string, AddressSuggestion[]>();

    async function fetchList(
        endpoint: string,
        code: Record<string, string> = {},
        signal?: AbortSignal,
    ): Promise<PsgcItem[]> {
        const controller = new AbortController();
        controllers.add(controller);
        const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });

        if (signal?.aborted) {
            controller.abort();
        }

        try {
            const response = await fetch(
                `/psgc/${endpoint}?${new URLSearchParams({ limit: '1000', ...code })}`,
                { signal: controller.signal },
            );

            if (!response.ok) {
                throw new Error('Address request failed');
            }

            return (await response.json()).data ?? [];
        } finally {
            signal?.removeEventListener('abort', abort);
            controllers.delete(controller);
        }
    }

    function init() {
        return (initialization ??= loadInitialAddress());
    }

    async function loadInitialAddress() {
        // Populate existing codes before watchers may clear dependent selections.
        loading.value = true;

        try {
            regions.value = await fetchList('regions');

            if (form.region_code) {
                [provinces.value, cities.value] = await Promise.all([
                    fetchList('provinces', { region_code: form.region_code }),
                    fetchList('cities', { region_code: form.region_code }),
                ]);
            }

            if (form.city_code) {
                barangays.value = await fetchList('barangays', {
                    city_code: form.city_code,
                });
            }
        } catch {
            if (alive) {
                error.value =
                    'Address choices couldn’t load. Close and reopen the form to retry.';
            }
        } finally {
            initializing = false;
            loading.value = false;
        }
    }

    // Changing a parent address choice invalidates its dependent choices.
    watch(
        () => form.region_code,
        async (code) => {
            if (initializing || autofilling) {
                return;
            }

            const request = regionRequest.start();
            selectionRequest.cancel();
            matchingLocation.value = false;
            cityRequest.cancel();
            form.province_code = '';
            form.city_code = '';
            form.barangay_code = '';
            provinces.value = [];
            cities.value = [];
            barangays.value = [];
            error.value = '';

            if (!code) {
                return;
            }

            try {
                const lists = await Promise.all([
                    fetchList(
                        'provinces',
                        { region_code: code },
                        request.signal,
                    ),
                    fetchList('cities', { region_code: code }, request.signal),
                ]);

                if (request.isCurrent()) {
                    [provinces.value, cities.value] = lists;
                }
            } catch {
                if (request.isCurrent()) {
                    error.value =
                        'Address choices couldn’t load. Select the region again to retry.';
                }
            }
        },
    );

    watch(
        () => form.province_code,
        () => {
            if (!initializing && !autofilling) {
                selectionRequest.cancel();
                matchingLocation.value = false;
                form.city_code = '';
                form.barangay_code = '';
            }
        },
    );

    watch(
        () => form.city_code,
        async (code) => {
            if (initializing || autofilling) {
                return;
            }

            const request = cityRequest.start();
            selectionRequest.cancel();
            matchingLocation.value = false;
            form.barangay_code = '';
            barangays.value = [];

            if (!code) {
                return;
            }

            try {
                const items = await fetchList(
                    'barangays',
                    { city_code: code },
                    request.signal,
                );

                if (request.isCurrent()) {
                    barangays.value = items;
                }
            } catch {
                if (request.isCurrent()) {
                    error.value =
                        'Barangays couldn’t load. Select the city again to retry.';
                }
            }
        },
    );

    async function loadSuggestions() {
        const request = geocodeRequest.start();
        const city = cities.value.find(
            (item) => item.code === form.city_code,
        )?.name;
        const query = [form.address.trim(), city].filter(Boolean).join(', ');

        try {
            const matches =
                suggestionCache.get(query) ??
                (await searchAddresses(query, request.signal));

            if (!request.isCurrent()) {
                return;
            }

            suggestionCache.set(query, matches);
            suggestions.value = matches;
            searched.value = true;
        } catch {
            if (request.isCurrent()) {
                suggestionError.value =
                    'Address suggestions are unavailable. You can type the address and place the pin manually.';
            }
        } finally {
            if (request.isCurrent()) {
                locating.value = false;
            }
        }
    }

    function requestSuggestions() {
        cancelLookup();
        suggestionError.value = '';
        searched.value = false;

        if (
            form.address.trim().length < 3 ||
            form.address === selectedAddress
        ) {
            return;
        }

        locating.value = true;
        suggestionTimer = setTimeout(() => void loadSuggestions(), 350);
    }

    function cancelLookup() {
        clearTimeout(suggestionTimer);
        geocodeRequest.cancel();
        locating.value = false;
        suggestions.value = [];
        searched.value = false;
        suggestionError.value = '';
    }

    async function selectSuggestion(suggestion: AddressSuggestion) {
        cancelLookup();
        regionRequest.cancel();
        cityRequest.cancel();
        const request = selectionRequest.start();
        matchingLocation.value = true;
        selectedAddress = suggestion.label;
        form.address = suggestion.label;
        form.lat = suggestion.lat;
        form.lng = suggestion.lng;
        error.value = '';

        try {
            await init();

            if (!request.isCurrent()) {
                return;
            }

            await applyAddressCodes('', '', '', '');

            if (!suggestion.city) {
                throw new Error('City missing from suggestion');
            }

            const cityMatches = matchingPlaces(
                await fetchList(
                    'cities',
                    {
                        name: normalizePlaceName(suggestion.city).replace(
                            / city$/,
                            '',
                        ),
                    },
                    request.signal,
                ),
                suggestion.city,
            );
            let city = cityMatches.length === 1 ? cityMatches[0] : undefined;

            if (!city && suggestion.province) {
                const provinceMatches = matchingPlaces(
                    await fetchList(
                        'provinces',
                        {
                            name: suggestion.province,
                        },
                        request.signal,
                    ),
                    suggestion.province,
                );
                const candidates = cityMatches.filter((item) =>
                    provinceMatches.some(
                        (province) => province.code === item.province_code,
                    ),
                );
                city = candidates.length === 1 ? candidates[0] : undefined;
            }

            if (!city?.region_code) {
                throw new Error('No unique PSGC city match');
            }

            const [regionProvinces, regionCities, cityBarangays] =
                await Promise.all([
                    fetchList(
                        'provinces',
                        { region_code: city.region_code },
                        request.signal,
                    ),
                    fetchList(
                        'cities',
                        { region_code: city.region_code },
                        request.signal,
                    ),
                    fetchList(
                        'barangays',
                        { city_code: city.code },
                        request.signal,
                    ),
                ]);

            if (!request.isCurrent()) {
                return;
            }

            provinces.value = regionProvinces;
            cities.value = regionCities;
            barangays.value = cityBarangays;
            const barangayMatches = suggestion.barangay
                ? matchingPlaces(cityBarangays, suggestion.barangay)
                : [];
            const barangay =
                barangayMatches.length === 1 ? barangayMatches[0] : undefined;
            await applyAddressCodes(
                city.region_code,
                city.province_code ?? '',
                city.code,
                barangay?.code ?? '',
            );

            if (request.isCurrent() && !barangay) {
                error.value =
                    'Region and city filled. Please choose the barangay; the address result did not identify a unique match.';
            }
        } catch {
            if (request.isCurrent()) {
                error.value =
                    'Please choose the region, city, and barangay manually. This address could not be matched to PSGC.';
            }
        } finally {
            if (request.isCurrent()) {
                matchingLocation.value = false;
            }
        }
    }

    async function applyAddressCodes(
        region: string,
        province: string,
        city: string,
        barangay: string,
    ) {
        // Apply the hierarchy together so parent watchers do not clear matched children.
        autofilling = true;
        form.region_code = region;
        form.province_code = province;
        form.city_code = city;
        form.barangay_code = barangay;
        await nextTick();
        autofilling = false;
    }

    // Suggestions never change coordinates until the owner selects a result.
    watch(
        () => form.address,
        (value) => {
            if (value !== selectedAddress) {
                selectionRequest.cancel();
                matchingLocation.value = false;
            }
        },
        { flush: 'sync' },
    );

    watch(
        () => [
            form.address,
            form.region_code,
            form.province_code,
            form.city_code,
            form.barangay_code,
        ],
        requestSuggestions,
    );

    onUnmounted(() => {
        alive = false;
        regionRequest.cancel();
        cityRequest.cancel();
        geocodeRequest.cancel();
        clearTimeout(suggestionTimer);
        controllers.forEach((c) => c.abort());
        selectionRequest.cancel();
    });

    return {
        regions,
        provinces,
        cities: computed(() =>
            cities.value.filter(
                (c) =>
                    !form.province_code ||
                    c.province_code === form.province_code,
            ),
        ),
        barangays,
        init,
        loading,
        error,
        locating,
        requestSuggestions,
        cancelLookup,
        suggestions,
        selectSuggestion,
        suggestionError,
        searched,
        matchingLocation,
    };
}
