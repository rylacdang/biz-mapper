import type { Coordinates } from '@/types';

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
