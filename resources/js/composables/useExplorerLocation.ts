import { onUnmounted, ref } from 'vue';
import type { UserLocation } from '@/types';

export function useExplorerLocation() {
    const locating = ref(false);
    const error = ref('');
    const accuracy = ref(0);

    let generation = 0;

    function locate(
        onLocation: (location: UserLocation, first: boolean) => void,
        onUnavailable: () => void,
    ) {
        const request = ++generation;
        let bestAccuracy = Infinity;
        let receivedLocation = false;
        error.value = '';

        if (!navigator.geolocation) {
            error.value = 'This browser cannot provide your location.';
            onUnavailable();

            return;
        }

        locating.value = true;

        function accept(position: GeolocationPosition) {
            if (request !== generation) {
                return;
            }

            locating.value = false;

            if (position.coords.accuracy > bestAccuracy) {
                return;
            }

            const first = !receivedLocation;
            receivedLocation = true;
            bestAccuracy = position.coords.accuracy;
            accuracy.value = bestAccuracy;
            onLocation(
                {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: bestAccuracy,
                },
                first,
            );
        }

        function fail(failure: GeolocationPositionError) {
            // A failed refinement must not replace a usable initial location.
            if (request !== generation || receivedLocation) {
                return;
            }

            locating.value = false;
            const messages: Record<number, string> = {
                1: 'Location permission is blocked. Allow location for this site, then tap My location.',
                2: 'Your device could not determine its location. Check location services, then tap My location.',
                3: 'Finding your location took too long. Tap My location to retry.',
            };
            error.value =
                messages[failure.code] ??
                'Location is unavailable. Tap My location to retry.';
            onUnavailable();
        }

        function refine() {
            if (request !== generation) {
                return;
            }

            navigator.geolocation.getCurrentPosition(accept, fail, {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 15000,
            });
        }

        // Prefer a quick cached/network fix before waiting for precise positioning.
        navigator.geolocation.getCurrentPosition(
            (position) => {
                accept(position);
                refine();
            },
            (failure) => {
                if (failure.code === 1) {
                    fail(failure);
                } else {
                    refine();
                }
            },
            { enableHighAccuracy: false, maximumAge: 60000, timeout: 5000 },
        );
    }

    onUnmounted(() => {
        generation++;
    });

    return { locating, error, accuracy, locate };
}
