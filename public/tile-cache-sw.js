const CACHE_NAME = 'map-tiles-v1';
const MAX_CACHE_SIZE = 2000; // Max number of tiles to keep cached

// Tile URL patterns to cache
const TILE_PATTERNS = [
    'server.arcgisonline.com',
    'basemaps.cartocdn.com',
];

function isTileRequest(url) {
    return TILE_PATTERNS.some((pattern) => url.includes(pattern));
}

// Trim cache if it gets too large
async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length > maxItems) {
        // Delete oldest entries (FIFO)
        const deleteCount = keys.length - maxItems;
        for (let i = 0; i < deleteCount; i++) {
            await cache.delete(keys[i]);
        }
    }
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only intercept tile requests
    if (!isTileRequest(request.url)) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            // Try cache first
            const cachedResponse = await cache.match(request);
            if (cachedResponse) {
                return cachedResponse;
            }

            // Not in cache — fetch from network
            try {
                const networkResponse = await fetch(request);

                // Only cache successful responses
                if (networkResponse.ok) {
                    cache.put(request, networkResponse.clone());
                    // Trim in background
                    trimCache(CACHE_NAME, MAX_CACHE_SIZE);
                }

                return networkResponse;
            } catch (error) {
                // If the request was aborted, propagate the error so Leaflet knows it was cancelled
                if (error.name === 'AbortError') {
                    throw error;
                }
                // Network failed and no cache — return a transparent 1x1 pixel
                return new Response(
                    Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVQI12NgAAIABQABNjN9GQAAAABJRU5ErkJggg=='), c => c.charCodeAt(0)),
                    { headers: { 'Content-Type': 'image/png' } }
                );
            }
        })
    );
});
