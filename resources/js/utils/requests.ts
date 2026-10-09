// Reject stale completions even when a response resolved before cancellation.
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
