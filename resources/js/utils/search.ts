import { DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import type { SearchState } from '@/types';

export function searchParams(state: SearchState, page = 1): URLSearchParams {
    const params = new URLSearchParams({
        lat: String(state.lat),
        lng: String(state.lng),
        radius: String(state.radius),
        keyword: state.keyword,
        category: state.category,
    });

    if (page > 1) {
        params.set('page', String(page));
    }

    return params;
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
