import type { FeatureCollection } from 'geojson';
import type { GeoJSONSource, Map as CityMap, Marker } from 'maplibre-gl';
import { onUnmounted, ref, shallowRef } from 'vue';
import { MAP_STYLE } from '@/constants/map';
import { maplibregl } from '@/maps/maplibre';
import type {
    Category,
    Coordinates,
    BusinessMarker,
    SearchState,
    UserLocation,
} from '@/types';
import type { WalkingRoute } from '@/types/routing';
import { categoryIconImage } from '@/utils/categoryIcons';

export function useCityMap(
    select: (id: number) => void,
    move: (center: Coordinates) => void,
) {
    // Native resources are disposed together when the explorer leaves the page.
    let map: CityMap | undefined;
    let disposed = false;
    let buildings = false;
    let resize: ResizeObserver | undefined;
    let userMarker: Marker | undefined;
    let userLocation: UserLocation | undefined;

    const userMarkerElement = shallowRef<HTMLElement>();

    const ready = ref(false);
    const loadingTiles = ref(false);
    let loadingTimer: ReturnType<typeof setTimeout> | undefined;
    const error = ref('');
    const is3d = ref(true);

    let selectedId: number | null = null;
    const reduced = () =>
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Generate category sprites locally so map pins need no external icon service.
    async function markerImage(category: Category) {
        const canvas = document.createElement('canvas');
        canvas.width = 88;
        canvas.height = 104;
        const ctx = canvas.getContext('2d')!;
        ctx.shadowColor = '#172b4d33';
        ctx.shadowBlur = 10;
        ctx.shadowOffsetY = 4;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(44, 42, 34, 0, Math.PI * 2);
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;
        ctx.fillStyle = category.color;
        ctx.beginPath();
        ctx.arc(44, 42, 28, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.moveTo(34, 70);
        ctx.lineTo(44, 90);
        ctx.lineTo(54, 70);
        ctx.fill();
        const icon = await categoryIconImage(category.icon);

        if (icon) {
            ctx.drawImage(icon, 26, 24, 36, 36);
        }

        return ctx.getImageData(0, 0, 88, 104);
    }

    // Tile loading continues after setup, including whenever the camera moves.
    function updateTileLoading() {
        if (!map || disposed) {
            return;
        }

        if (map.areTilesLoaded()) {
            clearTimeout(loadingTimer);
            loadingTimer = undefined;
            loadingTiles.value = false;

            return;
        }

        if (!loadingTiles.value && loadingTimer === undefined) {
            // Avoid flashing a status message for tiles already in the browser cache.
            loadingTimer = setTimeout(() => {
                loadingTimer = undefined;

                if (!disposed && map) {
                    loadingTiles.value = !map.areTilesLoaded();
                }
            }, 250);
        }
    }

    function init(
        container: HTMLElement,
        center: Coordinates,
        categories: Category[],
        done: () => void,
        initialZoom = 15,
        initialPitch = window.innerWidth < 768 ? 35 : 45,
    ) {
        try {
            map = new maplibregl.Map({
                container,
                style: MAP_STYLE,
                center: [center.lng, center.lat],
                zoom: initialZoom,
                pitch: initialPitch,
                bearing: -18,
                maxPitch: 65,
                attributionControl: { compact: true },
                // Keep loading coarser tiles so zooming can fill gaps progressively.
                cancelPendingTileRequestsWhileZooming: false,
            });
            // Start mobile credits collapsed; the native info button keeps every link accessible.
            const collapseMobileCredits = () => {
                if (window.innerWidth >= 768) {
                    return;
                }

                const credits = container.querySelector<HTMLDetailsElement>(
                    '.maplibregl-ctrl-attrib.maplibregl-compact-show[open]',
                );
                credits?.querySelector<HTMLElement>('summary')?.click();
            };
            map.once('load', collapseMobileCredits);
            map.on('resize', collapseMobileCredits);
            map.on('sourcedataloading', updateTileLoading);
            map.on('sourcedata', updateTileLoading);
            map.on('sourcedataabort', updateTileLoading);
            map.on('idle', updateTileLoading);
            resize = new ResizeObserver(() => map?.resize());
            resize.observe(container);
            map.on('error', () => {
                updateTileLoading();
                error.value =
                    'Some map tiles couldn’t load. You can still browse places.';
            });
            map.on('moveend', () => {
                updateTileLoading();
                const current = map?.getCenter();

                if (current) {
                    move({ lat: current.lat, lng: current.lng });
                }
            });
            map.on('load', async () => {
                const categoryImages = await Promise.all(
                    categories.map(markerImage),
                );

                if (disposed || !map) {
                    return;
                }

                error.value = '';

                // Insert buildings below labels, interpolating height at neighborhood zoom.
                const style = map.getStyle();
                const source = Object.keys(style.sources).find(
                    (key) => style.sources[key].type === 'vector',
                );
                const label = style.layers.find(
                    (layer) => layer.type === 'symbol',
                )?.id;

                if (source) {
                    map.addLayer(
                        {
                            id: 'city-buildings',
                            type: 'fill-extrusion',
                            source,
                            'source-layer': 'building',
                            minzoom: 14,
                            filter: ['!=', ['get', 'hide_3d'], true],
                            paint: {
                                'fill-extrusion-color': '#d4deed',
                                'fill-extrusion-height': [
                                    'interpolate',
                                    ['linear'],
                                    ['zoom'],
                                    14,
                                    0,
                                    15,
                                    ['coalesce', ['get', 'render_height'], 6],
                                ],
                                'fill-extrusion-base': [
                                    'coalesce',
                                    ['get', 'render_min_height'],
                                    0,
                                ],
                                'fill-extrusion-opacity': 0.92,
                            },
                        },
                        label,
                    );
                    buildings = true;
                }

                // Clusters, pins, and the selection highlight share one business source.
                categories.forEach((c, index) =>
                    map!.addImage(`category-${c.id}`, categoryImages[index], {
                        pixelRatio: 2,
                    }),
                );
                map.addSource('places', {
                    type: 'geojson',
                    data: { type: 'FeatureCollection', features: [] },
                    cluster: true,
                    clusterMaxZoom: 14,
                    clusterRadius: 45,
                });
                map.addLayer({
                    id: 'clusters',
                    type: 'circle',
                    source: 'places',
                    filter: ['has', 'point_count'],
                    paint: {
                        'circle-color': '#2563eb',
                        'circle-radius': 23,
                        'circle-stroke-color': '#fff',
                        'circle-stroke-width': 4,
                    },
                });
                map.addLayer({
                    id: 'cluster-count',
                    type: 'symbol',
                    source: 'places',
                    filter: ['has', 'point_count'],
                    layout: {
                        'text-field': ['get', 'point_count_abbreviated'],
                        'text-size': 13,
                    },
                    paint: { 'text-color': '#fff' },
                });
                map.addLayer({
                    id: 'selection',
                    type: 'circle',
                    source: 'places',
                    filter: ['==', ['get', 'id'], -1],
                    paint: {
                        'circle-radius': 26,
                        'circle-color': '#2563eb',
                        'circle-opacity': 0.2,
                        'circle-stroke-width': 2,
                        'circle-stroke-color': '#2563eb',
                    },
                });
                map.addLayer({
                    id: 'pins',
                    type: 'symbol',
                    source: 'places',
                    filter: ['!', ['has', 'point_count']],
                    layout: {
                        'icon-image': ['get', 'icon'],
                        'icon-anchor': 'bottom',
                        'icon-allow-overlap': true,
                    },
                });
                // Both map pins and result buttons select by the same business ID.
                map.on('click', 'pins', (e) => {
                    const id = e.features?.[0]?.properties.id;

                    if (id) {
                        select(Number(id));
                    }
                });
                map.on('click', 'clusters', async (e) => {
                    const f = e.features?.[0];

                    if (!f || f.geometry.type !== 'Point') {
                        return;
                    }

                    const zoom = await (
                        map!.getSource('places') as GeoJSONSource
                    ).getClusterExpansionZoom(Number(f.properties.cluster_id));

                    if (!disposed) {
                        map?.easeTo({
                            center: f.geometry.coordinates as [number, number],
                            zoom,
                            duration: reduced() ? 0 : 500,
                        });
                    }
                });
                ['pins', 'clusters'].forEach((layer) => {
                    map!.on('mouseenter', layer, () => {
                        map!.getCanvas().style.cursor = 'pointer';
                    });
                    map!.on('mouseleave', layer, () => {
                        map!.getCanvas().style.cursor = '';
                    });
                });
                ready.value = true;
                updateTileLoading();
                updateUserAccuracy();
                done();
            });
        } catch {
            error.value =
                'Your browser can’t display the 3D map. Browse places in the list instead.';
        }
    }

    function setPlaces(places: BusinessMarker[]) {
        if (!ready.value || !map) {
            return;
        }

        (map.getSource('places') as GeoJSONSource).setData({
            type: 'FeatureCollection',
            features: places.map((b) => ({
                type: 'Feature',
                geometry: {
                    type: 'Point',
                    coordinates: [Number(b.lng), Number(b.lat)],
                },
                properties: { id: b.id, icon: `category-${b.category_id}` },
            })),
        });
        highlight(selectedId);
    }

    function moveCenter(center: Coordinates) {
        const current = map?.getCenter();
        const padding = map?.getPadding();

        if (
            !current ||
            (Math.abs(current.lat - center.lat) < 0.0000001 &&
                Math.abs(current.lng - center.lng) < 0.0000001 &&
                padding &&
                Object.values(padding).every((value) => value === 0))
        ) {
            return;
        }

        map?.jumpTo({
            center: [center.lng, center.lat],
            padding: { left: 0, right: 0, top: 0, bottom: 0 },
        });
    }

    function highlight(id: number | null) {
        selectedId = id;

        if (ready.value) {
            map?.setFilter('selection', ['==', ['get', 'id'], id ?? -1]);
        }
    }

    function focus(
        place: Coordinates,
        panelVisible = true,
        minimumZoom = 16,
        pitch?: number,
    ) {
        // Camera padding keeps the selected pin clear of the active panel and controls.
        const mobile = window.innerWidth < 768;
        map?.easeTo({
            center: [place.lng, place.lat],
            zoom: Math.max(map.getZoom(), minimumZoom),
            pitch: pitch ?? map.getPitch(),
            padding: {
                left: mobile || !panelVisible ? 24 : 380,
                right: 70,
                top: 88,
                bottom: mobile && panelVisible ? window.innerHeight * 0.46 : 72,
            },
            duration: !ready.value || reduced() ? 0 : 650,
        });
    }

    function setUserLocation(location: UserLocation) {
        if (!map || disposed) {
            return;
        }

        userLocation = location;

        if (!userMarker) {
            // A DOM marker remains above extruded buildings and does not join business clusters.
            const element = document.createElement('div');
            element.className = 'z-1 pointer-events-none';
            userMarkerElement.value = element;
            userMarker = new maplibregl.Marker({
                element,
                anchor: 'bottom',
                offset: [0, 10],
                pitchAlignment: 'viewport',
                rotationAlignment: 'viewport',
            })
                .setLngLat([location.lng, location.lat])
                .addTo(map);
        } else {
            userMarker.setLngLat([location.lng, location.lat]);
        }

        updateUserAccuracy();
    }

    function updateUserAccuracy() {
        if (!ready.value || !map || !userLocation) {
            return;
        }

        // Browser accuracy is measured in meters; reuse the kilometer-based geodesic ring.
        const data = radiusGeometry(userLocation, userLocation.accuracy / 1000);

        if (map.getSource('user-accuracy')) {
            (map.getSource('user-accuracy') as GeoJSONSource).setData(data);

            return;
        }

        map.addSource('user-accuracy', { type: 'geojson', data });
        map.addLayer(
            {
                id: 'user-accuracy-fill',
                type: 'fill',
                source: 'user-accuracy',
                paint: { 'fill-color': '#d97706', 'fill-opacity': 0.1 },
            },
            'clusters',
        );
        map.addLayer(
            {
                id: 'user-accuracy-line',
                type: 'line',
                source: 'user-accuracy',
                paint: {
                    'line-color': '#d97706',
                    'line-opacity': 0.7,
                    'line-width': 1.5,
                    'line-dasharray': [2, 2],
                },
            },
            'clusters',
        );
    }

    function setRadius(state: SearchState | null) {
        if (!ready.value || !map) {
            return;
        }

        const data = radiusGeometry(state, state?.radius ?? 0);

        if (map.getSource('radius')) {
            (map.getSource('radius') as GeoJSONSource).setData(data);
        } else {
            map.addSource('radius', { type: 'geojson', data });
            map.addLayer(
                {
                    id: 'radius-fill',
                    type: 'fill',
                    source: 'radius',
                    paint: { 'fill-color': '#2563eb', 'fill-opacity': 0.14 },
                },
                'clusters',
            );
            map.addLayer(
                {
                    id: 'radius-line',
                    type: 'line',
                    source: 'radius',
                    paint: {
                        'line-color': '#2563eb',
                        'line-width': 3,
                        'line-opacity': 0.95,
                    },
                },
                'clusters',
            );
        }
    }

    function radiusGeometry(
        center: Coordinates | null,
        radius: number,
    ): FeatureCollection {
        // Use a geodesic ring so distance stays in kilometers across map latitudes.
        const points: number[][] = [];

        if (center) {
            for (let i = 0; i <= 96; i++) {
                const angle = (i / 96) * Math.PI * 2;
                const d = radius / 6371;
                const lat = (center.lat * Math.PI) / 180;
                const lng = (center.lng * Math.PI) / 180;
                const targetLat = Math.asin(
                    Math.sin(lat) * Math.cos(d) +
                        Math.cos(lat) * Math.sin(d) * Math.cos(angle),
                );
                const targetLng =
                    lng +
                    Math.atan2(
                        Math.sin(angle) * Math.sin(d) * Math.cos(lat),
                        Math.cos(d) - Math.sin(lat) * Math.sin(targetLat),
                    );
                points.push([
                    (targetLng * 180) / Math.PI,
                    (targetLat * 180) / Math.PI,
                ]);
            }
        }

        return {
            type: 'FeatureCollection',
            features: center
                ? [
                      {
                          type: 'Feature',
                          properties: {},
                          geometry: { type: 'Polygon', coordinates: [points] },
                      },
                  ]
                : [],
        };
    }

    function setRoute(route: WalkingRoute | null, panelOpen = false) {
        if (!ready.value || !map) {
            return;
        }

        if (!map.getSource('walking-route')) {
            map.addSource('walking-route', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });

            const before = map
                .getStyle()
                .layers.find((layer) => layer.id === 'selection')?.id;

            map.addLayer(
                {
                    id: 'walking-route-outline',
                    type: 'line',
                    source: 'walking-route',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#ffffff', 'line-width': 9 },
                },
                before,
            );
            map.addLayer(
                {
                    id: 'walking-route-line',
                    type: 'line',
                    source: 'walking-route',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': '#2563eb', 'line-width': 5 },
                },
                before,
            );
        }

        (map.getSource('walking-route') as GeoJSONSource).setData({
            type: 'FeatureCollection',
            features: route
                ? [
                      {
                          type: 'Feature',
                          properties: {},
                          geometry: route.geometry,
                      },
                  ]
                : [],
        });

        if (route) {
            const bounds = new maplibregl.LngLatBounds();
            route.geometry.coordinates.forEach(([lng, lat]) =>
                bounds.extend([lng, lat]),
            );
            const mobile = window.innerWidth < 768;

            map.fitBounds(bounds, {
                padding: {
                    top: mobile ? 155 : 120,
                    right: mobile ? 65 : 100,
                    bottom:
                        mobile && panelOpen ? window.innerHeight * 0.52 : 90,
                    left: !mobile && panelOpen ? 420 : 55,
                },
                maxZoom: 18.5,
                duration: reduced() ? 0 : 700,
            });
        }
    }

    // Fit the visible collection page while keeping pins clear of the explorer panel.
    function fitPlaces(
        places: Coordinates[],
        panelOpen: boolean,
        bottomInset = 0,
    ) {
        if (!map || !ready.value || !places.length) {
            return;
        }

        const bounds = new maplibregl.LngLatBounds();

        for (const place of places) {
            bounds.extend([place.lng, place.lat]);
        }

        const mobile = window.innerWidth < 768;

        map.fitBounds(bounds, {
            padding: {
                top: mobile ? 115 : 110,
                right: mobile ? 55 : 90,
                bottom:
                    (mobile && panelOpen
                        ? window.innerHeight * 0.52 + 20
                        : 80) + bottomInset,
                left: !mobile && panelOpen ? 390 : 40,
            },
            maxZoom: 18,
            duration: reduced() ? 0 : 700,
        });
    }

    function toggle3d() {
        is3d.value = !is3d.value;
        map?.easeTo({
            pitch: is3d.value ? (window.innerWidth < 768 ? 35 : 45) : 0,
            duration: reduced() ? 0 : 500,
        });

        if (buildings) {
            map?.setLayoutProperty(
                'city-buildings',
                'visibility',
                is3d.value ? 'visible' : 'none',
            );
        }
    }

    onUnmounted(() => {
        disposed = true;
        clearTimeout(loadingTimer);
        resize?.disconnect();
        userMarker?.remove();
        map?.remove();
    });

    return {
        init,
        ready,
        loadingTiles,
        error,
        is3d,
        userMarkerElement,
        setUserLocation,
        setPlaces,
        moveCenter,
        highlight,
        focus,
        setRadius,
        setRoute,
        toggle3d,
        fitPlaces,
        zoom: (delta: number) =>
            map?.easeTo({
                zoom: map.getZoom() + delta,
                duration: reduced() ? 0 : 250,
            }),
        north: () => map?.easeTo({ bearing: 0, duration: reduced() ? 0 : 400 }),
    };
}
