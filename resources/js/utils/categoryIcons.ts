import {
    Store,
    Utensils,
    Coffee,
    Croissant,
    ShoppingBag,
    Wrench,
    Pill,
    BedDouble,
    Scissors,
    Monitor,
    Banknote,
    UtensilsCrossed,
    GraduationCap,
    Music,
    ShoppingCart,
    WashingMachine,
    Building2,
    Printer,
    Plane,
    Wifi,
} from 'lucide-vue-next';
import { h, render } from 'vue';
import type { Component } from 'vue';

const icons: Record<string, Component> = {
    store: Store,
    utensils: Utensils,
    coffee: Coffee,
    croissant: Croissant,
    'shopping-bag': ShoppingBag,
    wrench: Wrench,
    pill: Pill,
    'bed-double': BedDouble,
    scissors: Scissors,
    monitor: Monitor,
    banknote: Banknote,
    'utensils-crossed': UtensilsCrossed,
    'graduation-cap': GraduationCap,
    music: Music,
    'shopping-cart': ShoppingCart,
    'washing-machine': WashingMachine,
    'building-2': Building2,
    printer: Printer,
    plane: Plane,
    wifi: Wifi,
};

// Older page payloads can still contain the previous Heroicons names.
const legacyIcons: Record<string, string> = {
    BuildingStorefrontIcon: 'store',
    FireIcon: 'utensils',
    SparklesIcon: 'coffee',
    CakeIcon: 'croissant',
    ShoppingBagIcon: 'shopping-bag',
    WrenchScrewdriverIcon: 'wrench',
    HeartIcon: 'pill',
    HomeIcon: 'bed-double',
    FaceSmileIcon: 'scissors',
    ComputerDesktopIcon: 'monitor',
    BanknotesIcon: 'banknote',
    TruckIcon: 'utensils-crossed',
    AcademicCapIcon: 'graduation-cap',
    MusicalNoteIcon: 'music',
    ShoppingCartIcon: 'shopping-cart',
    ScissorsIcon: 'washing-machine',
    BuildingOffice2Icon: 'building-2',
    PaintBrushIcon: 'printer',
    GlobeAltIcon: 'plane',
    WifiIcon: 'wifi',
};

export function categoryIcon(key?: string): Component {
    return icons[legacyIcons[key ?? ''] ?? key ?? ''] ?? Store;
}

export function categoryIconImage(
    key: string,
): Promise<HTMLImageElement | null> {
    // Rasterize the same Vue icon used in filters for MapLibre's canvas sprites.
    const container = document.createElement('div');
    render(
        h(categoryIcon(key), { size: 36, color: '#fff', strokeWidth: 2 }),
        container,
    );
    const svg = container.innerHTML;
    render(null, container);

    return new Promise((resolve) => {
        const image = new Image();
        const finish = (result: HTMLImageElement | null) => {
            image.onload = null;
            image.onerror = null;
            resolve(result);
        };
        image.onload = () => finish(image);
        image.onerror = () => finish(null);
        image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    });
}
