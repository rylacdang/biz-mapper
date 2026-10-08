import { computed, onUnmounted, ref, watch } from 'vue';
import { latestRequest } from '@/lib/explorer';
import type { PsgcItem } from '@/types';

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

    const regionRequest = latestRequest();
    const cityRequest = latestRequest();
    const geocodeRequest = latestRequest();
    const controllers = new Set<AbortController>();
    let initializing = true;
    let alive = true;

    async function fetchList(
        endpoint: string,
        code: Record<string, string> = {},
        signal?: AbortSignal,
    ): Promise<PsgcItem[]> {
        const controller = new AbortController();
        controllers.add(controller);
        const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });

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

    async function init() {
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
            if (initializing) {
                return;
            }

            const request = regionRequest.start();
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
            if (!initializing) {
                form.city_code = '';
                form.barangay_code = '';
            }
        },
    );

    watch(
        () => form.city_code,
        async (code) => {
            if (initializing) {
                return;
            }

            const request = cityRequest.start();
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

    async function findOnMap() {
        // Only this explicit action geocodes; editing or saving never moves the pin.
        if (!form.address.trim()) {
            error.value = 'Enter an address first.';

            return;
        }

        const request = geocodeRequest.start();
        locating.value = true;
        error.value = '';
        const names = [
            barangays.value.find((x) => x.code === form.barangay_code)?.name,
            cities.value.find((x) => x.code === form.city_code)?.name,
            'Philippines',
        ];

        const query = new URLSearchParams({
            q: [form.address, ...names].filter(Boolean).join(', '),
            format: 'json',
            countrycodes: 'ph',
            limit: '1',
        });

        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?${query}`,
                { signal: request.signal },
            );

            if (!response.ok) {
                throw new Error('Lookup failed');
            }

            const data = (await response.json()) as {
                lat: string;
                lon: string;
            }[];

            if (!request.isCurrent()) {
                return;
            }

            if (!data.length) {
                error.value =
                    'No matching address. Place the pin on the map instead.';

                return;
            }

            form.lat = Number(data[0].lat);
            form.lng = Number(data[0].lon);
        } catch {
            if (request.isCurrent()) {
                error.value =
                    'Address lookup is unavailable. You can place the pin manually.';
            }
        } finally {
            if (request.isCurrent()) {
                locating.value = false;
            }
        }
    }

    function cancelLookup() {
        geocodeRequest.cancel();
        locating.value = false;
    }

    // A manual pin move also calls this, preventing a pending lookup from overriding it.
    watch(
        () => [
            form.address,
            form.region_code,
            form.province_code,
            form.city_code,
            form.barangay_code,
        ],
        cancelLookup,
    );

    onUnmounted(() => {
        alive = false;
        regionRequest.cancel();
        cityRequest.cancel();
        geocodeRequest.cancel();
        controllers.forEach((c) => c.abort());
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
        findOnMap,
        cancelLookup,
    };
}
