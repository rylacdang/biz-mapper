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
