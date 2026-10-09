import type { SearchBusiness, SearchState, Coordinates } from '@/types';

export const DEFAULT_CENTER: Coordinates = { lat: 14.5547, lng: 121.0244 };
export const DEFAULT_SEARCH_RADIUS = 0.5;
export const MAP_STYLE = 'https://tiles.openfreemap.org/styles/bright';

export function formatDistance(kilometers: number): string {
    return kilometers < 1
        ? `${Math.round(kilometers * 1000)} m`
        : `${Number(kilometers.toFixed(1))} km`;
}

export function distanceInKilometers(
    from: Coordinates,
    to: Coordinates,
): number {
    const radians = (value: number) => (value * Math.PI) / 180;
    const latitude = radians(to.lat - from.lat);
    const longitude = radians(to.lng - from.lng);
    const arc =
        Math.sin(latitude / 2) ** 2 +
        Math.cos(radians(from.lat)) *
            Math.cos(radians(to.lat)) *
            Math.sin(longitude / 2) ** 2;

    return 6371 * 2 * Math.asin(Math.sqrt(Math.min(1, arc)));
}

export function photoUrl(path?: string | null): string | undefined {
    if (!path) {
        return undefined;
    }

    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    return `/storage/${path.replace(/^\//, '')}`;
}

export function websiteUrl(url?: string | null): string | undefined {
    if (!url) {
        return undefined;
    }

    try {
        const parsed = new URL(url);

        return ['https:', 'http:'].includes(parsed.protocol)
            ? parsed.href
            : undefined;
    } catch {
        return undefined;
    }
}

export function searchParams(state: SearchState): URLSearchParams {
    return new URLSearchParams({
        lat: String(state.lat),
        lng: String(state.lng),
        radius: String(state.radius),
        keyword: state.keyword,
        category: state.category,
    });
}

export function readSearch(url: string): SearchState | null {
    const params = new URL(url, 'http://localhost').searchParams;

    if (!params.has('lat') || !params.has('lng') || !params.has('radius')) {
        return null;
    }

    const lat = Number(params.get('lat'));
    const lng = Number(params.get('lng'));
    const radius = Number(params.get('radius'));

    if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng) ||
        !Number.isFinite(radius) ||
        Math.abs(lat) > 90 ||
        Math.abs(lng) > 180 ||
        radius < DEFAULT_SEARCH_RADIUS ||
        radius > 50
    ) {
        return null;
    }

    return {
        lat,
        lng,
        radius,
        keyword: params.get('keyword') ?? '',
        category: params.get('category') ?? 'all',
    };
}

// A generation guard also rejects responses already resolved before cancellation.
export function latestRequest() {
    let generation = 0;
    let controller: AbortController | undefined;

    return {
        start() {
            controller?.abort();
            controller = new AbortController();
            const current = ++generation;

            return {
                signal: controller.signal,
                isCurrent: () => current === generation,
            };
        },
        cancel() {
            generation++;
            controller?.abort();
        },
    };
}

export function listingCoordinates(
    listing: Pick<SearchBusiness, 'lat' | 'lng'>,
): Coordinates {
    return { lat: Number(listing.lat), lng: Number(listing.lng) };
}

export function listingSubmission<T extends Coordinates>(draft: T): T {
    // Normalize numeric inputs without changing the owner's chosen location.
    return { ...draft, ...listingCoordinates(draft) };
}
