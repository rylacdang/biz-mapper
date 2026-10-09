import { onUnmounted, ref, shallowRef } from 'vue';
import { directions } from '@/routes/businesses';
import type { Coordinates, SearchBusiness } from '@/types';
import type { WalkingRoute } from '@/types/routing';
import { latestRequest } from '@/utils/requests';

export function useWalkingRoute(origin: () => Coordinates | null) {
    const route = shallowRef<WalkingRoute | null>(null);
    const loading = ref(false);
    const error = ref('');
    const destination = shallowRef<SearchBusiness | null>(null);
    const requests = latestRequest();

    function clear() {
        requests.cancel();
        route.value = null;
        destination.value = null;
        loading.value = false;
        error.value = '';
    }

    async function start(place: SearchBusiness) {
        const request = requests.start();
        destination.value = place;
        route.value = null;
        error.value = '';
        loading.value = true;

        try {
            const position = origin() ?? (await locate());

            if (!request.isCurrent()) {
                return;
            }

            const response = await fetch(
                directions.url(place.slug, {
                    query: { lat: position.lat, lng: position.lng },
                }),
                {
                    signal: AbortSignal.any([
                        request.signal,
                        AbortSignal.timeout(15000),
                    ]),
                    headers: { Accept: 'application/json' },
                },
            );
            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message ??
                        'Walking directions are unavailable. Please retry.',
                );
            }

            if (request.isCurrent()) {
                route.value = data as WalkingRoute;
            }
        } catch (failure) {
            if (request.isCurrent()) {
                error.value =
                    failure instanceof Error
                        ? failure.message
                        : 'Unable to find walking directions.';
            }
        } finally {
            if (request.isCurrent()) {
                loading.value = false;
            }
        }
    }

    function locate(): Promise<Coordinates> {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('This browser cannot provide your location.'));

                return;
            }

            navigator.geolocation.getCurrentPosition(
                ({ coords }) =>
                    resolve({ lat: coords.latitude, lng: coords.longitude }),
                () =>
                    reject(
                        new Error(
                            'Allow location access, then retry directions.',
                        ),
                    ),
                { enableHighAccuracy: true, maximumAge: 60000, timeout: 10000 },
            );
        });
    }

    onUnmounted(clear);

    return { route, loading, error, destination, start, clear };
}
