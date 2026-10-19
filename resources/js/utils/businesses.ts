import type { BusinessOffering } from '@/types';

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

export function offeringPrice(item: BusinessOffering): string | null {
    if (item.price === null || !item.currency) {
        return null;
    }

    const format = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: item.currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
    const minimum = format.format(Number(item.price));
    const amount =
        item.price_max !== null && Number(item.price_max) !== Number(item.price)
            ? `${minimum} \u2013 ${format.format(Number(item.price_max))}`
            : minimum;

    return item.price_unit ? `${amount} / ${item.price_unit}` : amount;
}
