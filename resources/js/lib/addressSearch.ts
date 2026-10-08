import type { AddressSuggestion } from '@/types';

interface PhotonFeature {
    geometry?: { coordinates?: number[] };
    properties?: {
        osm_id?: number;
        osm_type?: string;
        name?: string;
        housenumber?: string;
        street?: string;
        district?: string;
        locality?: string;
        city?: string;
        state?: string;
        country?: string;
        countrycode?: string;
    };
}

export async function searchAddresses(
    query: string,
    signal: AbortSignal,
): Promise<AddressSuggestion[]> {
    const endpoint = import.meta.env.VITE_ADDRESS_SEARCH_URL;

    if (!endpoint) {
        throw new Error('Address search is not configured');
    }

    const url = new URL(endpoint);
    url.search = new URLSearchParams({
        q: query,
        countrycode: 'PH',
        limit: '5',
        lang: 'en',
    }).toString();
    const response = await fetch(url, {
        signal: AbortSignal.any([signal, AbortSignal.timeout(8000)]),
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Address search failed');
    }

    const data = (await response.json()) as { features?: PhotonFeature[] };

    if (!Array.isArray(data.features)) {
        throw new Error('Invalid address search response');
    }

    return data.features.flatMap((feature, index) => {
        const [lng, lat] = feature.geometry?.coordinates ?? [];
        const place = feature.properties;

        if (
            !place ||
            !Number.isFinite(lat) ||
            !Number.isFinite(lng) ||
            Math.abs(lat) > 90 ||
            Math.abs(lng) > 180 ||
            place.countrycode?.toUpperCase() !== 'PH'
        ) {
            return [];
        }

        const street = [place.housenumber, place.street]
            .filter(Boolean)
            .join(' ');
        const label = [
            ...new Set(
                [
                    place.name,
                    street,
                    place.district,
                    place.city,
                    place.state,
                    place.country,
                ].filter(Boolean),
            ),
        ].join(', ');

        return label
            ? [
                  {
                      id: `${place.osm_type}-${place.osm_id}-${index}`,
                      label,
                      lat,
                      lng,
                      city: place.city,
                      province: place.state,
                      barangay: place.district ?? place.locality,
                  },
              ]
            : [];
    });
}
