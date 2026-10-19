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

export interface UserLocation extends Coordinates {
    accuracy: number;
}

export interface AddressSuggestion extends Coordinates {
    id: number | string;
    label: string;
    city?: string;
    province?: string;
    barangay?: string;
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
    social_links?: Partial<
        Record<'facebook' | 'instagram' | 'tiktok', string | null>
    >;
    cover_photo?: string | null;
    logo?: string | null;
    gallery_photos?: string[];
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
    region_code?: string;
    is_city?: boolean;
}

export interface SearchState extends Coordinates {
    keyword: string;
    category: string;
    radius: number;
}

export interface GalleryPhotoDraft {
    path: string | null;
    file: File | null;
}

// Map pins carry no profile content or owner fields.
export interface BusinessMarker extends Coordinates {
    id: number;
    slug: string;
    category_id: number | string | null;
}

export interface SearchPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface BusinessOffering {
    id: number;
    type: 'product' | 'service';
    name: string;
    description: string | null;
    photo: string | null;
    price: string | null;
    price_max: string | null;
    currency: string | null;
    price_unit: string | null;
}

export interface OfferingPage {
    data: BusinessOffering[];
    meta: SearchPagination;
}

export interface BusinessOfferingDraft extends BusinessOffering {
    file: File | null;
    remove_photo: boolean;
}
