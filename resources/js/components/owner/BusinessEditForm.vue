<script setup lang="ts">
import { ref, watch, nextTick, onUnmounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import business from '@/routes/owner/business';
import type { Category, Business } from '@/types';

const props = defineProps<{
    currentTab: string;
    categoriesList: Category[];
    listing: Business;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'tabChange', tab: string): void;
}>();

const isConfirmingDelete = ref(false);
const isDeleting = ref(false);

const submitDelete = () => {
    isDeleting.value = true;
    router.delete(business.destroy(props.listing.id).url, {
        onSuccess: () => {
            isConfirmingDelete.value = false;
            isDeleting.value = false;
            emit('close');
        },
        onError: () => {
            isDeleting.value = false;
        },
    });
};

// Prefill form from existing listing
const form = useForm({
    category_id: props.listing.category_id,
    name: props.listing.name,
    description: props.listing.description,
    address: props.listing.address,
    lat: props.listing.lat,
    lng: props.listing.lng,
    phone: props.listing.phone ?? '',
    website: props.listing.website ?? '',
    cover_photo: null as File | null,
    logo: null as File | null,
    hours: props.listing.hours ?? {
        monday: { open: '09:00', close: '18:00', closed: false },
        tuesday: { open: '09:00', close: '18:00', closed: false },
        wednesday: { open: '09:00', close: '18:00', closed: false },
        thursday: { open: '09:00', close: '18:00', closed: false },
        friday: { open: '09:00', close: '18:00', closed: false },
        saturday: { open: '10:00', close: '16:00', closed: false },
        sunday: { open: '00:00', close: '00:00', closed: true },
    },
    _method: 'PUT',
});

// Upload Handlers
const handleCoverUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.cover_photo = target.files[0];
    }
};

const handleLogoUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
    }
};

// Form Submission — POST with _method spoofing for file uploads
const submitEditForm = () => {
    form.post(business.update(props.listing.id).url, {
        onSuccess: () => {
            emit('close');
        },
    });
};

const handleNextStep = () => {
    form.clearErrors();
    let isValid = true;

    if (props.currentTab === 'general') {
        if (!form.name || form.name.trim() === '') {
            form.setError('name', 'Listing name is required.');
            isValid = false;
        }
        if (!form.category_id) {
            form.setError('category_id', 'Category is required.');
            isValid = false;
        }
        if (!form.description || form.description.trim() === '') {
            form.setError('description', 'Description is required.');
            isValid = false;
        } else if (form.description.trim().length < 10) {
            form.setError(
                'description',
                'Description must be at least 10 characters.',
            );
            isValid = false;
        }
        if (!form.address || form.address.trim() === '') {
            form.setError('address', 'Street address is required.');
            isValid = false;
        }

        if (isValid) {
            emit('tabChange', 'details');
        }
    } else if (props.currentTab === 'details') {
        emit('tabChange', 'schedule');
    } else if (props.currentTab === 'schedule') {
        emit('tabChange', 'position');
    }
};

// Map States & Reference
const modalMapContainer = ref<HTMLDivElement | null>(null);
let L: any = null;
let modalMap: any = null;
let modalMarker: any = null;

const initModalMap = async () => {
    if (!modalMapContainer.value) return;

    const LeafletModule = await import('leaflet');
    L = LeafletModule.default || LeafletModule;

    modalMap = L.map(modalMapContainer.value, {
        center: [form.lat, form.lng],
        zoom: 12,
        zoomControl: false,
    });

    L.control.zoom({ position: 'bottomright' }).addTo(modalMap);

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        { attribution: '&copy; CARTO' },
    ).addTo(modalMap);

    const iconHtml = `
        <div class="relative flex items-center justify-center cursor-pointer" style="width: 36px; height: 36px;">
            <div class="relative flex items-center justify-center h-8 w-8 rounded-full border-2 border-white shadow-xl text-white bg-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="10" r="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
            </div>
        </div>
    `;

    const customIcon = L.divIcon({
        html: iconHtml,
        className: 'custom-div-icon',
        iconSize: [36, 36],
        iconAnchor: [18, 18],
    });

    modalMarker = L.marker([form.lat, form.lng], {
        icon: customIcon,
        draggable: true,
    }).addTo(modalMap);

    modalMarker.on('dragend', () => {
        const position = modalMarker.getLatLng();
        form.lat = Number(position.lat.toFixed(7));
        form.lng = Number(position.lng.toFixed(7));
    });

    modalMap.on('click', (e: any) => {
        const position = e.latlng;
        modalMarker.setLatLng(position);
        form.lat = Number(position.lat.toFixed(7));
        form.lng = Number(position.lng.toFixed(7));
    });
};

// Watch for tab switching to position
watch(
    () => props.currentTab,
    (newTab) => {
        if (newTab === 'position') {
            nextTick(() => {
                if (!modalMap) {
                    initModalMap();
                } else {
                    modalMap.invalidateSize();
                }
            });
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
    }
});
</script>

<template>
    <form
        @submit.prevent="submitEditForm"
        class="flex flex-col overflow-y-auto p-6 text-xs"
    >
        <!-- TAB 1: GENERAL INFO -->
        <div v-show="currentTab === 'general'" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Listing Name
                        <span class="text-rose-500">*</span></label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="e.g. Manila Coffee Roasters"
                        class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                    />
                    <span
                        v-if="form.errors.name"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.name }}
                    </span>
                </div>
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Category <span class="text-rose-500">*</span></label
                    >
                    <select
                        v-model="form.category_id"
                        required
                        class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                    >
                        <option
                            value=""
                            disabled
                            class="bg-gray-900 text-gray-500"
                        >
                            Select Category
                        </option>
                        <option
                            v-for="cat in categoriesList"
                            :key="cat.id"
                            :value="cat.id"
                            class="bg-gray-900 text-gray-200"
                        >
                            {{ cat.name }}
                        </option>
                    </select>
                    <span
                        v-if="form.errors.category_id"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.category_id }}
                    </span>
                </div>
            </div>

            <div class="space-y-2">
                <label class="block font-bold text-gray-300"
                    >Description <span class="text-rose-500">*</span></label
                >
                <textarea
                    v-model="form.description"
                    required
                    rows="4"
                    placeholder="Briefly describe your business offerings, services, specialty..."
                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                ></textarea>
                <span
                    v-if="form.errors.description"
                    class="text-[10px] text-red-400"
                >
                    {{ form.errors.description }}
                </span>
            </div>

            <div class="space-y-2">
                <label class="block font-bold text-gray-300"
                    >Street Address <span class="text-rose-500">*</span></label
                >
                <input
                    v-model="form.address"
                    type="text"
                    required
                    placeholder="Complete building number, street name, city, province"
                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                />
                <span
                    v-if="form.errors.address"
                    class="text-[10px] text-red-400"
                >
                    {{ form.errors.address }}
                </span>
            </div>
        </div>

        <!-- TAB 2: DETAILS -->
        <div v-show="currentTab === 'details'" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Contact Number</label
                    >
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="+63 917 123 4567"
                        class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                    />
                    <span
                        v-if="form.errors.phone"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.phone }}
                    </span>
                </div>
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Website Address</label
                    >
                    <input
                        v-model="form.website"
                        type="text"
                        placeholder="https://example.com"
                        class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-3 py-2.5 text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                    />
                    <span
                        v-if="form.errors.website"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.website }}
                    </span>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-4 border-t border-gray-500/20 pt-4 sm:grid-cols-2"
            >
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Cover Photo Banner (Max 5MB)
                        <span class="text-gray-500"
                            >— leave empty to keep current</span
                        ></label
                    >
                    <div
                        class="flex items-center justify-center rounded-xl border border-dashed border-gray-500/35 bg-gray-900/20 p-4"
                    >
                        <div class="space-y-1 text-center text-gray-500">
                            <svg
                                class="mx-auto h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                            <input
                                type="file"
                                @change="handleCoverUpload"
                                accept="image/*"
                                class="cursor-pointer text-[10px] font-bold text-primary-light"
                            />
                            <span
                                v-if="form.cover_photo"
                                class="mt-1 block text-[10px] font-bold text-emerald-400"
                            >
                                ✓ Ready: {{ form.cover_photo.name }}
                            </span>
                        </div>
                    </div>
                    <span
                        v-if="form.errors.cover_photo"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.cover_photo }}
                    </span>
                </div>
                <div class="space-y-2">
                    <label class="block font-bold text-gray-300"
                        >Brand Logo (Max 2MB)
                        <span class="text-gray-500"
                            >— leave empty to keep current</span
                        ></label
                    >
                    <div
                        class="flex items-center justify-center rounded-xl border border-dashed border-gray-500/35 bg-gray-900/20 p-4"
                    >
                        <div class="space-y-1 text-center text-gray-500">
                            <svg
                                class="mx-auto h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <input
                                type="file"
                                @change="handleLogoUpload"
                                accept="image/*"
                                class="cursor-pointer text-[10px] font-bold text-primary-light"
                            />
                            <span
                                v-if="form.logo"
                                class="mt-1 block text-[10px] font-bold text-emerald-400"
                            >
                                ✓ Ready: {{ form.logo.name }}
                            </span>
                        </div>
                    </div>
                    <span
                        v-if="form.errors.logo"
                        class="text-[10px] text-red-400"
                    >
                        {{ form.errors.logo }}
                    </span>
                </div>
            </div>
        </div>

        <!-- TAB 3: SCHEDULE -->
        <div v-show="currentTab === 'schedule'" class="space-y-3">
            <p
                class="mb-3 border-b border-gray-500/10 pb-2 text-[10px] font-bold tracking-widest text-gray-500 uppercase"
            >
                Set Operating Hours
            </p>

            <div
                v-for="(dayData, dayName) in form.hours"
                :key="dayName"
                class="flex flex-col justify-between gap-3 border-b border-gray-500/5 pb-2.5 sm:flex-row sm:items-center"
            >
                <span
                    class="w-24 text-xs font-extrabold text-gray-300 capitalize"
                    >{{ dayName }}</span
                >
                <div class="flex flex-wrap items-center gap-4">
                    <label
                        class="flex cursor-pointer items-center gap-1.5 font-bold text-gray-400"
                    >
                        <input
                            type="checkbox"
                            v-model="dayData.closed"
                            class="rounded border-gray-500/30 bg-gray-900 text-indigo-600 focus:ring-indigo-500"
                        />
                        Closed
                    </label>
                    <div
                        v-show="!dayData.closed"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="dayData.open"
                            type="time"
                            class="rounded-lg border border-gray-500/20 bg-gray-900/40 px-2 py-1 text-gray-200 focus:outline-none"
                        />
                        <span class="font-medium text-gray-500">to</span>
                        <input
                            v-model="dayData.close"
                            type="time"
                            class="rounded-lg border border-gray-500/20 bg-gray-900/40 px-2 py-1 text-gray-200 focus:outline-none"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: POSITION -->
        <div v-show="currentTab === 'position'" class="flex flex-col gap-4">
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-500/10 pb-2"
            >
                <p
                    class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                >
                    Geospatial Marker Pin Placement
                </p>
                <div class="flex items-center gap-3 text-[11px] font-semibold">
                    <div>
                        <span class="text-gray-500">Lat:</span>
                        <span class="font-mono text-gray-200">{{
                            form.lat
                        }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Lng:</span>
                        <span class="font-mono text-gray-200">{{
                            form.lng
                        }}</span>
                    </div>
                </div>
            </div>
            <p class="text-[10px] leading-relaxed text-gray-500">
                Click on the map or drag the blue marker to update the directory
                location coordinates.
            </p>

            <!-- Mini Map Inside Modal -->
            <div
                ref="modalMapContainer"
                class="z-0 h-[320px] w-full rounded-xl border border-gray-500/20"
            ></div>
        </div>

        <!-- Footer Action Buttons -->
        <div
            class="mt-8 flex items-center justify-between border-t border-gray-500/15 bg-gray-900/40 pt-4"
        >
            <template v-if="isConfirmingDelete">
                <div class="flex items-center gap-2 font-bold text-red-400">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 animate-pulse"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                    <span>Delete listing permanently?</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="isConfirmingDelete = false"
                        class="rounded-xl border border-gray-500/30 bg-gray-900 px-4 py-2 font-bold text-gray-400 transition hover:text-white"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="submitDelete"
                        :disabled="isDeleting"
                        class="flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 font-bold text-white shadow transition hover:bg-rose-500 active:scale-98 disabled:opacity-50"
                    >
                        <svg
                            v-if="isDeleting"
                            class="h-3 w-3 animate-spin text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Yes, Delete
                    </button>
                </div>
            </template>
            <template v-else>
                <div class="flex items-center gap-2">
                    <button
                        v-show="currentTab !== 'general'"
                        type="button"
                        @click="
                            emit(
                                'tabChange',
                                currentTab === 'details'
                                    ? 'general'
                                    : currentTab === 'schedule'
                                      ? 'details'
                                      : 'schedule',
                            )
                        "
                        class="rounded-xl border border-gray-500/30 bg-gray-900 px-4 py-2 font-bold text-gray-400 transition hover:text-white"
                    >
                        Back
                    </button>
                    <button
                        type="button"
                        @click="isConfirmingDelete = true"
                        class="flex items-center gap-1.5 rounded-xl border border-rose-500/35 bg-rose-500/10 px-4 py-2.5 font-bold text-rose-400 transition hover:bg-rose-500/20 hover:text-rose-300"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                        Delete
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-show="currentTab !== 'position'"
                        type="button"
                        @click="handleNextStep"
                        class="rounded-xl bg-primary px-5 py-2 font-bold text-white shadow transition hover:bg-primary-light"
                    >
                        Next Step
                    </button>
                    <button
                        v-show="currentTab === 'position'"
                        type="submit"
                        :disabled="form.processing"
                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-primary px-6 py-2.5 font-bold text-white shadow transition hover:bg-primary-light active:scale-98 disabled:opacity-50"
                    >
                        <svg
                            v-if="form.processing"
                            class="h-3.5 w-3.5 animate-spin text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </template>
        </div>
    </form>
</template>
