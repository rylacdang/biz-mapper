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
    is_saved?: boolean;
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
    shortcut?: string;
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

export interface DiscoveryShortcut {
    id: string;
    label: string;
    icon: string;
    categories: string[];
}

export interface SavedPlaceChange {
    businessId: number;
    userId: number;
    saved: boolean;
}

export interface SavedPlacesPage {
    data: SearchBusiness[];
    meta: SearchPagination;
}

export interface PlaceCollection {
    id: number;
    is_public: boolean;
    share_url: string | null;
    name: string;
    places_count: number;
}

export interface CollectionPlace extends SearchBusiness {
    saved_id: number;
    in_collection: boolean;
}

export interface CollectionsPage {
    data: PlaceCollection[];
    meta: SearchPagination;
}

export interface CollectionPlacesPage {
    data: CollectionPlace[];
    meta: SearchPagination;
    collection?: PlaceCollection;
}

export interface PublicCollection {
    name: string;
    share_token: string;
    places_count: number;
    url: string;
    map_url: string;
}

export interface PublicCollectionPlacesPage {
    data: SearchBusiness[];
    meta: SearchPagination;
}

export interface BusinessReview {
    id: number;
    rating: number;
    body: string;
    status: 'published' | 'hidden';
    author: string;
    created_at: string;
    updated_at: string;
    can_edit: boolean;
    reply: { body: string; created_at: string; updated_at: string } | null;
}

export interface BusinessFeedback {
    reviews: { data: BusinessReview[]; meta: SearchPagination };
    summary: { count: number; average: number | null };
    own_review: BusinessReview | null;
    can_review: boolean;
    can_reply: boolean;
}

export interface ReviewSummary {
    status:
        | 'insufficient_reviews'
        | 'pending'
        | 'generating'
        | 'ready'
        | 'unavailable';
    text: string | null;
    generated_at: string | null;
    review_count: number;
    sampled_review_count: number;
}
