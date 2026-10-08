export function formatBusinessTime(time?: string | null): string {
    if (!time || !/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) {
        return '—';
    }

    const [hour, minute] = time.split(':');
    const value = Number(hour);

    return `${value % 12 || 12}:${minute} ${value >= 12 ? 'PM' : 'AM'}`;
}
