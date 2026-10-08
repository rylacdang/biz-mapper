export interface Category {
    id: number | string;
    name: string;
    icon: string;
    color: string;
}

export interface Coordinates {
    lat: number;
    lng: number;
}

export interface OpeningHours {
    open: string;
    close: string;
    closed: boolean;
}

// Public discovery data does not include the fields required to edit a listing.
export interface SearchBusiness extends Coordinates {
    id: number;
    name: string;
    slug: string;
    description: string;
    address: string;
    phone?: string | null;
    website?: string | null;
    cover_photo?: string | null;
    logo?: string | null;
    category?: Category | null;
    hours?: Record<string, OpeningHours>;
    distance?: number;
}

// Full owner records are used by the listing drawer and management actions.
export interface Business extends SearchBusiness {
    user_id: number;
    category_id: number;
    status: 'pending' | 'active';
    region_code?: string | null;
    province_code?: string | null;
    city_code?: string | null;
    barangay_code?: string | null;
}

export interface PsgcItem {
    code: string;
    name: string;
    province_code?: string | null;
}

export interface SearchState extends Coordinates {
    keyword: string;
    category: string;
    radius: number;
}
