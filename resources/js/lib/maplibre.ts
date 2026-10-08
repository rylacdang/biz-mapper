import * as maplibregl from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

// Vite must bundle the worker and its shared module together, including in dev.
maplibregl.setWorkerUrl(workerUrl);

export { maplibregl };
