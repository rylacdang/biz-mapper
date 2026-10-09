import type { LineString } from 'geojson';

export interface WalkingRoute {
    geometry: LineString;
    distance: number;
    duration: number;
}
