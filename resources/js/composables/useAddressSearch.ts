import { ref, watch, computed, nextTick } from 'vue';

interface PsgcItem {
    code: string;
    name: string;
}

interface NominatimSuggestion {
    display_name: string;
    lat: string;
    lon: string;
    address?: {
        suburb?: string;
        neighbourhood?: string;
        village?: string;
        city?: string;
        municipality?: string;
        town?: string;
        province?: string;
        state?: string;
        region?: string;
    };
}

const BASE_URL = '/psgc';

async function fetchPsgc(
    endpoint: string,
    params: Record<string, string> = {},
): Promise<PsgcItem[]> {
    const query = new URLSearchParams(params).toString();
    const url = query
        ? `${BASE_URL}/${endpoint}?${query}`
        : `${BASE_URL}/${endpoint}`;

    try {
        const response = await fetch(url);
        const json = await response.json();

        return json.data ?? [];
    } catch {
        return [];
    }
}

export function useAddressSearch(form: any) {
    const regions = ref<PsgcItem[]>([]);
    const provinces = ref<PsgcItem[]>([]);
    const cities = ref<PsgcItem[]>([]);
    const barangays = ref<PsgcItem[]>([]);

    const selectedRegionCode = ref('');
    const selectedProvinceCode = ref('');
    const selectedCityCode = ref('');
    const selectedBarangayCode = ref('');

    const addressSearch = ref('');
    const addressSuggestions = ref<NominatimSuggestion[]>([]);
    const showSuggestions = ref(false);
    const isLoadingSuggestions = ref(false);

    const isInitializing = ref(false);

    // If no provinces found (NCR case), skip province step
    const hasProvinces = computed(() => provinces.value.length > 0);

    const loadRegions = async () => {
        regions.value = await fetchPsgc('regions', { limit: '100' });
    };

    // Watchers for selections updating the dropdown lists
    watch(selectedRegionCode, async (code) => {
        if (isInitializing.value) {
            return;
        }

        provinces.value = [];
        cities.value = [];
        barangays.value = [];
        selectedProvinceCode.value = '';
        selectedCityCode.value = '';
        selectedBarangayCode.value = '';

        form.region_code = code;
        form.province_code = '';
        form.city_code = '';
        form.barangay_code = '';

        if (!code) {
            return;
        }

        // Load provinces
        provinces.value = await fetchPsgc('provinces', {
            region_code: code,
            limit: '200',
        });

        // Load all cities for this region (regardless of province) to support HUCs (Highly Urbanized Cities)
        cities.value = await fetchPsgc('cities', {
            region_code: code,
            limit: '300',
        });
    });

    watch(selectedProvinceCode, (code) => {
        if (isInitializing.value) {
            return;
        }

        selectedCityCode.value = '';
        selectedBarangayCode.value = '';

        form.province_code = code;
        form.city_code = '';
        form.barangay_code = '';
    });

    watch(selectedCityCode, async (code) => {
        if (isInitializing.value) {
            return;
        }

        barangays.value = [];
        selectedBarangayCode.value = '';

        form.city_code = code;
        form.barangay_code = '';

        if (code) {
            const selectedCity = cities.value.find((c) => c.code === code);

            if (selectedCity) {
                selectedProvinceCode.value = selectedCity.province_code || '';
                form.province_code = selectedCity.province_code || '';
            }

            barangays.value = await fetchPsgc('barangays', {
                city_code: code,
                limit: '1000',
            });
        }
    });

    watch(selectedBarangayCode, (code) => {
        if (isInitializing.value) {
            return;
        }

        form.barangay_code = code;
    });

    // Nominatim suggestions search
    let searchTimeout: any = null;
    const onAddressSearchInput = () => {
        const val = addressSearch.value;

        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        if (!val || val.length < 3) {
            addressSuggestions.value = [];

            return;
        }

        isLoadingSuggestions.value = true;
        searchTimeout = setTimeout(async () => {
            try {
                // Focus on Philippines to get local suggestions, request address details
                const queryUrl = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(
                    val,
                )}&format=json&addressdetails=1&countrycodes=ph&limit=5`;
                const response = await fetch(queryUrl);

                if (response.ok) {
                    addressSuggestions.value = await response.json();
                }
            } catch (e) {
                console.error('OSM address suggestion search error:', e);
            } finally {
                isLoadingSuggestions.value = false;
            }
        }, 300);
    };

    // Selecting a Nominatim address suggestion
    const selectSuggestion = async (suggestion: NominatimSuggestion) => {
        isInitializing.value = true;

        // Reset old selections
        selectedRegionCode.value = '';
        selectedProvinceCode.value = '';
        selectedCityCode.value = '';
        selectedBarangayCode.value = '';

        form.region_code = '';
        form.province_code = '';
        form.city_code = '';
        form.barangay_code = '';

        provinces.value = [];
        cities.value = [];
        barangays.value = [];

        addressSearch.value = suggestion.display_name;
        form.address = suggestion.display_name;
        addressSuggestions.value = [];

        // Set coordinates
        form.lat = parseFloat(suggestion.lat);
        form.lng = parseFloat(suggestion.lon);

        const addr = suggestion.address;

        if (!addr) {
            isInitializing.value = false;

            return;
        }

        // Extract names
        const regionName = addr.region || addr.state || '';
        const provinceName = addr.province || '';
        const cityName = addr.city || addr.municipality || addr.town || '';
        const barangayName =
            addr.suburb || addr.neighbourhood || addr.village || '';

        // Match Region Name
        let matchedRegion = regions.value.find((r) =>
            normalizeMatch(r.name, regionName),
        );

        // Fallback for NCR / Metro Manila
        if (!matchedRegion && regionName) {
            const cleanRegion = regionName
                .toLowerCase()
                .replace(/[^a-z0-9]/g, '');

            if (
                cleanRegion.includes('manila') ||
                cleanRegion.includes('ncr') ||
                cleanRegion.includes('nationalcapital')
            ) {
                matchedRegion = regions.value.find(
                    (r) => r.code === '1300000000',
                );
            }
        }

        if (matchedRegion) {
            selectedRegionCode.value = matchedRegion.code;
            form.region_code = matchedRegion.code;

            // Load provinces for this region
            provinces.value = await fetchPsgc('provinces', {
                region_code: matchedRegion.code,
                limit: '200',
            });

            // Load all cities for this region (regardless of province) to support HUCs (Highly Urbanized Cities)
            cities.value = await fetchPsgc('cities', {
                region_code: matchedRegion.code,
                limit: '300',
            });

            if (provinceName && provinces.value.length > 0) {
                const matchedProvince = provinces.value.find((p) =>
                    normalizeMatch(p.name, provinceName),
                );

                if (matchedProvince) {
                    selectedProvinceCode.value = matchedProvince.code;
                    form.province_code = matchedProvince.code;
                }
            }

            // Match City
            if (cityName && cities.value.length > 0) {
                const matchedCity = cities.value.find((c) =>
                    normalizeMatch(c.name, cityName),
                );

                if (matchedCity) {
                    selectedCityCode.value = matchedCity.code;
                    form.city_code = matchedCity.code;

                    // Auto-select province if HUC matches or city belongs to a province
                    if (matchedCity.province_code) {
                        selectedProvinceCode.value = matchedCity.province_code;
                        form.province_code = matchedCity.province_code;
                    }

                    // Load barangays for this city
                    barangays.value = await fetchPsgc('barangays', {
                        city_code: matchedCity.code,
                        limit: '1000',
                    });

                    // Match Barangay
                    if (barangayName && barangays.value.length > 0) {
                        const matchedBarangay = barangays.value.find((b) =>
                            normalizeMatch(b.name, barangayName),
                        );

                        if (matchedBarangay) {
                            selectedBarangayCode.value = matchedBarangay.code;
                            form.barangay_code = matchedBarangay.code;
                        }
                    }
                }
            }
        }

        nextTick(() => {
            isInitializing.value = false;
        });
    };

    // Normalizes strings to match names (ignoring 'barangay', 'city of', etc.)
    const normalizeMatch = (
        psgcName: string,
        nominatimName: string,
    ): boolean => {
        const clean = (str: string) =>
            str
                .toLowerCase()
                .replace(
                    /\b(barangay|brgy|city of|municipality of|province of|region)\b/g,
                    '',
                )
                .replace(/[^a-z0-9]/g, '')
                .trim();

        return (
            clean(psgcName) === clean(nominatimName) ||
            clean(psgcName).includes(clean(nominatimName)) ||
            clean(nominatimName).includes(clean(psgcName))
        );
    };

    // Manual geocoding in case they just typed and didn't select suggestions
    const geocodeAddress = async () => {
        if (!form.address) {
            return;
        }

        try {
            const query = `${form.address}, Philippines`;
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(
                    query,
                )}&format=json&limit=1`,
            );

            if (response.ok) {
                const data = await response.json();

                if (data && data.length > 0) {
                    form.lat = parseFloat(data[0].lat);
                    form.lng = parseFloat(data[0].lon);
                }
            }
        } catch (e) {
            console.error('Nominatim geocode address error:', e);
        }
    };

    const init = async () => {
        isInitializing.value = true;
        await loadRegions();

        if (form.region_code) {
            selectedRegionCode.value = form.region_code;
            provinces.value = await fetchPsgc('provinces', {
                region_code: form.region_code,
                limit: '200',
            });
            cities.value = await fetchPsgc('cities', {
                region_code: form.region_code,
                limit: '300',
            });
        }

        if (form.province_code) {
            selectedProvinceCode.value = form.province_code;
        }

        if (form.city_code) {
            selectedCityCode.value = form.city_code;
            barangays.value = await fetchPsgc('barangays', {
                city_code: form.city_code,
                limit: '1000',
            });
        }

        if (form.barangay_code) {
            selectedBarangayCode.value = form.barangay_code;
        }

        // Add a small delay and turn off initializing flag so manual changes are watched
        nextTick(() => {
            isInitializing.value = false;
        });
    };

    const filteredCities = computed(() => {
        if (!selectedProvinceCode.value) {
            return cities.value;
        }

        return cities.value.filter(
            (c) => c.province_code === selectedProvinceCode.value,
        );
    });

    return {
        regions,
        provinces,
        cities: filteredCities,
        barangays,
        selectedRegionCode,
        selectedProvinceCode,
        selectedCityCode,
        selectedBarangayCode,
        addressSearch,
        addressSuggestions,
        showSuggestions,
        isLoadingSuggestions,
        onAddressSearchInput,
        selectSuggestion,
        geocodeAddress,
        hasProvinces,
        init,
    };
}
