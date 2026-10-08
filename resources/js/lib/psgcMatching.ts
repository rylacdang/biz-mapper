import type { PsgcItem } from '@/types';

export function normalizePlaceName(name: string): string {
    return name
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/^(city of|municipality of|barangay|brgy\.?)\s+/, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

export function matchingPlaces(items: PsgcItem[], name: string): PsgcItem[] {
    const normalized = normalizePlaceName(name);
    const exact = items.filter(
        (item) => normalizePlaceName(item.name) === normalized,
    );

    if (exact.length) {
        return exact;
    }

    return items.filter(
        (item) =>
            normalizePlaceName(item.name).replace(/ city$/, '') ===
            normalized.replace(/ city$/, ''),
    );
}
