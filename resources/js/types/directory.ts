export interface Category {
    id: number | string;
    name: string;
    icon: string;
    color: string;
}

export interface Business {
    id: number;
    user_id: number;
    category_id: number;
    name: string;
    slug: string;
    description: string;
    address: string;
    lat: number;
    lng: number;
    phone?: string;
    website?: string;
    cover_photo?: string;
    logo?: string;
    hours?: Record<string, { open: string; close: string; closed: boolean }>;
    rating?: number;
    reviewsCount?: number;
    color?: string;
}

export interface ListingPin extends Omit<Business, 'category_id'> {
    category_id?: number;
    category?: string;
}
