<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { X, ArrowLeft, ArrowRight, ImagePlus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useExplorerLocation } from '@/composables/useExplorerLocation';
import { useListingAddress } from '@/composables/useListingAddress';
import { DEFAULT_CENTER } from '@/constants/map';
import LocationPicker from '@/pages/explorer/partials/listings/LocationPicker.vue';
import RequiredMark from '@/pages/explorer/partials/listings/RequiredMark.vue';
import StreetAddressInput from '@/pages/explorer/partials/listings/StreetAddressInput.vue';
import business from '@/routes/owner/businesses';
import type {
    AddressSuggestion,
    Business,
    Category,
    Coordinates,
    OpeningHours,
} from '@/types';
import { photoUrl } from '@/utils/businesses';

const props = defineProps<{
    listing: Business | null;
    categories: Category[];
    initialLocation?: Coordinates | null;
}>();

const emit = defineEmits<{ close: [] }>();

// Drawer state and step navigation
const dialog = ref<HTMLDialogElement>();

const step = ref(0);
const confirming = ref(false);
const deleting = ref(false);
const actionError = ref('');
const steps = ['Business', 'Photos & contact', 'Hours', 'Location'];
const days = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

// Copy saved hours into the draft so edits never mutate the page payload.
const hours: Record<string, OpeningHours> = Object.fromEntries(
    days.map((day) => [
        day,
        { open: '09:00', close: '18:00', closed: day === 'sunday' },
    ]),
);

const coordinates = props.listing
    ? { lat: Number(props.listing.lat), lng: Number(props.listing.lng) }
    : (props.initialLocation ?? DEFAULT_CENTER);

const form = useForm({
    category_id: String(props.listing?.category_id ?? ''),
    name: props.listing?.name ?? '',
    description: props.listing?.description ?? '',
    address: props.listing?.address ?? '',
    region_code: props.listing?.region_code ?? '',
    province_code: props.listing?.province_code ?? '',
    city_code: props.listing?.city_code ?? '',
    barangay_code: props.listing?.barangay_code ?? '',
    ...coordinates,
    phone: props.listing?.phone ?? '',
    website: props.listing?.website ?? '',
    cover_photo: null as File | null,
    logo: null as File | null,
    hours: Object.fromEntries(
        days.map((day) => [
            day,
            { ...(props.listing?.hours?.[day] ?? hours[day]) },
        ]),
    ),
    _method: props.listing ? 'PUT' : 'POST',
});

const address = useListingAddress(form);

const {
    regions,
    provinces,
    cities,
    barangays,
    error: addressError,
    locating: suggestionsLoading,
    suggestions,
    suggestionError,
    searched,
    matchingLocation,
} = address;

const preview = ref(photoUrl(props.listing?.cover_photo));
const logoPreview = ref(photoUrl(props.listing?.logo));
const urls: string[] = [];
const saved = computed(() => Boolean(props.listing));
const ownerLocation = useExplorerLocation();
const { error: locationError } = ownerLocation;
const positionReady = ref(Boolean(props.listing || props.initialLocation));

let locationChosen = false;
let applyingLocation = false;

// A pin move, numeric edit, or address lookup takes priority over device location.
watch(
    () => [form.lat, form.lng],
    () => {
        if (!applyingLocation) {
            locationChosen = true;
            positionReady.value = true;
        }
    },
    { flush: 'sync' },
);

onMounted(() => {
    dialog.value?.showModal();
    void address.init();

    if (!saved.value) {
        ownerLocation.locate(
            (position) => {
                if (locationChosen) {
                    return;
                }

                applyingLocation = true;
                form.lat = position.lat;
                form.lng = position.lng;
                applyingLocation = false;
                positionReady.value = true;
            },
            () => {
                positionReady.value = true;
            },
        );
    }
});

// Object URLs live only as long as this drawer instance.
onUnmounted(() => urls.forEach((url) => URL.revokeObjectURL(url)));

function handleUpload(event: Event, field: 'cover_photo' | 'logo') {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form[field] = file;

    const url = URL.createObjectURL(file);
    urls.push(url);

    if (field === 'cover_photo') {
        preview.value = url;
    } else {
        logoPreview.value = url;
    }
}

function handleDialogCancel(event: Event) {
    // File picker cancellation bubbles but must not close the listing drawer.
    if (event.target !== dialog.value) {
        return;
    }

    if (!form.processing && !deleting.value) {
        emit('close');
    }
}

function handleSelectAddress(suggestion: AddressSuggestion) {
    locationChosen = true;
    positionReady.value = true;
    void address.selectSuggestion(suggestion);
}

function handlePlacePin(coords: Coordinates) {
    // A manual choice wins even if an earlier address lookup is still pending.
    address.cancelLookup();
    locationChosen = true;
    form.lat = coords.lat;
    form.lng = coords.lng;
}

function handleCoordinateInput() {
    locationChosen = true;
    positionReady.value = true;
    address.cancelLookup();
}

function handleNextStep() {
    form.clearErrors();

    if (step.value === 0) {
        if (!form.name.trim()) {
            form.setError('name', 'Give your business a name.');
        }

        if (!form.category_id) {
            form.setError('category_id', 'Choose a category.');
        }

        if (form.description.trim().length < 10) {
            form.setError(
                'description',
                'Write at least 10 characters about your business.',
            );
        }
    }

    if (step.value === 1 && !saved.value && !form.cover_photo) {
        form.setError('cover_photo', 'Choose a cover photo.');
    }

    if (!Object.keys(form.errors).length) {
        step.value++;
    }
}

function validationStep(field: string): number {
    if (['name', 'category_id', 'description'].includes(field)) {
        return 0;
    }

    if (['cover_photo', 'logo', 'phone', 'website'].includes(field)) {
        return 1;
    }

    return field.startsWith('hours') ? 2 : 3;
}

function handleSubmit() {
    form.transform((draft) => ({
        ...draft,
        lat: Number(draft.lat),
        lng: Number(draft.lng),
    }));
    // Saving is deliberately independent of address lookup; the chosen pin is authoritative.
    form.post(
        saved.value
            ? business.update(props.listing!.id).url
            : business.store().url,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => emit('close'),
            onError: (errors) => {
                step.value = validationStep(Object.keys(errors)[0]);
            },
        },
    );
}

function handleDelete() {
    if (!props.listing) {
        return;
    }

    deleting.value = true;
    actionError.value = '';
    router.delete(business.destroy(props.listing.id).url, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: () => {
            actionError.value = 'The listing couldn’t be deleted. Try again.';
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <!-- Native dialog traps focus until the form closes -->
    <dialog
        ref="dialog"
        :class="[
            'fixed [inset:0_0_0_auto] m-0 h-[100dvh] max-h-[100dvh] w-140 max-w-full',
            'rounded-[24px_0_0_24px] border-0 bg-white p-0 text-explorer-ink',
            'shadow-[-10px_0_60px_#172b4d20] backdrop:bg-[#172b4d55] backdrop:backdrop-blur-[3px]',
            'max-md:w-full max-md:rounded-[0]',
        ]"
        aria-labelledby="listing-title"
        @cancel.prevent="handleDialogCancel"
    >
        <form
            class="flex h-full flex-col"
            @submit.prevent="step === 3 ? handleSubmit() : handleNextStep()"
        >
            <!-- Drawer heading and close action -->
            <header
                class="flex items-center justify-between border-b border-[#edf1f6] px-[30px] pt-[27px] pb-6 max-md:px-5 max-md:py-[22px]"
            >
                <div>
                    <span
                        class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                    >
                        YOUR BUSINESS, DISCOVERED
                    </span>

                    <h2
                        class="mx-0 mt-2 mb-0 text-[22px] tracking-[-0.8px] max-md:text-[20px]"
                        id="listing-title"
                    >
                        {{
                            saved ? 'Edit your listing' : 'Hello, neighborhood.'
                        }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-[#f3f6fb] text-[#768ba5]"
                    :disabled="form.processing || deleting"
                    aria-label="Close listing form"
                    @click="emit('close')"
                >
                    <X :size="22" />
                </button>
            </header>

            <!-- Four-step progress navigation -->
            <nav
                class="grid grid-cols-4 gap-2 border-b border-[#edf1f6] px-5 py-4 max-md:px-3"
                aria-label="Listing steps"
            >
                <button
                    :class="[
                        'group/step flex min-w-0 flex-col items-center gap-2 border-0 bg-transparent p-0 text-[10px] md:flex-row',
                        'text-[#a1aec0] data-[active=true]:font-[700]',
                        'data-[active=true]:text-explorer-blue',
                    ]"
                    v-for="(label, index) in steps"
                    :key="label"
                    type="button"
                    :data-active="step === index"
                    :data-complete="step > index"
                    :aria-current="step === index ? 'step' : undefined"
                    @click="index < step && (step = index)"
                >
                    <span
                        :class="[
                            'grid h-6 w-6 shrink-0 place-items-center rounded-[50%] bg-[#eff3f9] text-[10px]',
                            'group-data-[active=true]/step:bg-explorer-blue group-data-[active=true]/step:text-white',
                            'group-data-[complete=true]/step:bg-[#e9f7f1]',
                            'group-data-[complete=true]/step:text-explorer-green max-md:h-[21px] max-md:w-[21px]',
                        ]"
                    >
                        {{ index + 1 }}
                    </span>
                    <span
                        class="min-h-7 text-center leading-[14px] md:min-h-0 md:text-left"
                    >
                        {{ label }}
                    </span>
                </button>
            </nav>

            <div
                class="min-h-0 flex-1 overflow-auto p-[30px] max-md:px-5 max-md:py-[22px]"
            >
                <!-- Step 1: Business information -->
                <div v-if="step === 0">
                    <span
                        class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                    >
                        01 / THE INTRODUCTION
                    </span>

                    <h3 class="mx-0 my-[9px] text-[21px] tracking-[-0.6px]">
                        Tell us about your business
                    </h3>

                    <p
                        class="mb-[26px] text-[12px] leading-[1.7] text-[#8593a8]"
                    >
                        A little personality goes a long way.
                    </p>

                    <label
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        <span>Business name <RequiredMark /></span>
                        <input
                            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
                            v-model="form.name"
                            required
                            maxlength="255"
                            placeholder="Enter business name"
                        />
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.name }}
                    </p>

                    <label
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        <span>Category <RequiredMark /></span>
                        <select
                            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                            v-model="form.category_id"
                            required
                        >
                            <option value="">Choose a category</option>

                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.category_id }}
                    </p>

                    <label
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        <span>Description <RequiredMark /></span>
                        <textarea
                            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                            v-model="form.description"
                            required
                            minlength="10"
                            rows="5"
                            placeholder="What makes your place special?"
                        />
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Step 2: Photos and contact information -->
                <div v-else-if="step === 1">
                    <span
                        class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                    >
                        02 / FIRST IMPRESSIONS
                    </span>

                    <h3 class="mx-0 my-[9px] text-[21px] tracking-[-0.6px]">
                        Make a great first impression
                    </h3>

                    <p
                        class="mb-[26px] text-[12px] leading-[1.7] text-[#8593a8]"
                    >
                        Let people see what they can look forward to.
                    </p>

                    <label
                        :class="[
                            'group/cover relative mt-[17px] flex h-[185px] cursor-pointer flex-col items-center justify-center gap-2',
                            'overflow-hidden rounded-[13px] border border-dashed border-[#b8cbe6] bg-[#f2f6fc]',
                            'text-[11px] font-[700] text-[#6387bb] has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-explorer-blue has-[:focus-visible]:ring-offset-2',
                        ]"
                    >
                        <img
                            class="absolute h-full w-full object-cover"
                            v-if="preview"
                            :src="preview"
                            alt="Cover photo preview"
                        />

                        <span
                            :class="[
                                'pointer-events-none absolute inset-0 z-1 flex flex-col items-center justify-center gap-2 p-[14px]',
                                'transition-opacity duration-200 motion-reduce:transition-none',
                                preview
                                    ? 'bg-black/55 text-white opacity-0 backdrop-blur-sm group-hover/cover:opacity-100 group-has-[:focus-visible]/cover:opacity-100 [@media(hover:none)_and_(pointer:coarse)]:opacity-100'
                                    : 'text-[#6387bb]',
                            ]"
                        >
                            <ImagePlus :size="26" />
                            <span>
                                {{
                                    preview
                                        ? 'Change cover photo'
                                        : 'Choose a cover photo'
                                }}
                                <RequiredMark v-if="!saved" />
                            </span>
                            <small
                                :class="[
                                    'text-[10px] font-[400]',
                                    preview
                                        ? 'text-white/80'
                                        : 'text-[#90a0b6]',
                                ]"
                            >
                                JPG, PNG or WebP · up to 5 MB
                            </small>
                        </span>

                        <input
                            class="absolute inset-0 z-2 h-full w-full cursor-pointer opacity-0"
                            type="file"
                            accept="image/*"
                            aria-label="Cover photo"
                            :aria-required="!saved"
                            @change="handleUpload($event, 'cover_photo')"
                        />
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.cover_photo }}
                    </p>

                    <div
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        <span class="flex items-center gap-2">
                            Logo
                            <small
                                class="text-[10px] font-normal text-slate-400"
                            >
                                Optional
                            </small>
                        </span>

                        <label
                            class="relative flex cursor-pointer items-center gap-4 rounded-xl border border-explorer-line bg-white p-4 transition-colors hover:border-blue-300 hover:bg-blue-50/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-explorer-blue has-[:focus-visible]:ring-offset-2 motion-reduce:transition-none"
                        >
                            <span
                                class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-xl border border-explorer-line bg-slate-50"
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Business logo preview"
                                    class="h-full w-full object-contain p-1.5"
                                />
                                <ImagePlus
                                    v-else
                                    :size="24"
                                    class="text-slate-400"
                                    aria-hidden="true"
                                />
                            </span>

                            <span class="flex min-w-0 flex-1 flex-col gap-1.5">
                                <span
                                    class="truncate text-xs text-explorer-ink"
                                >
                                    {{
                                        form.logo?.name ?? 'Your business logo'
                                    }}
                                </span>
                                <span
                                    class="text-[10px] font-normal text-slate-400"
                                >
                                    JPG, PNG or WebP · up to 2 MB
                                </span>
                                <span
                                    class="text-xs font-semibold text-explorer-blue"
                                >
                                    {{
                                        logoPreview
                                            ? 'Change logo'
                                            : 'Choose logo'
                                    }}
                                </span>
                            </span>

                            <input
                                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                type="file"
                                accept="image/*"
                                aria-label="Logo"
                                @change="handleUpload($event, 'logo')"
                            />
                        </label>
                    </div>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.logo }}
                    </p>

                    <label
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        Phone number
                        <input
                            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
                            v-model="form.phone"
                            type="tel"
                            placeholder="09XX XXX XXXX"
                        />
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.phone }}
                    </p>

                    <label
                        class="mt-[17px] flex flex-col gap-2 text-[11px] font-[700]"
                    >
                        Website
                        <input
                            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
                            v-model="form.website"
                            type="url"
                            placeholder="https://yourbusiness.com"
                        />
                    </label>

                    <p
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ form.errors.website }}
                    </p>
                </div>

                <!-- Step 3: Operating hours -->
                <div v-else-if="step === 2">
                    <span
                        class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                    >
                        03 / COME ON OVER
                    </span>

                    <h3 class="mx-0 my-[9px] text-[21px] tracking-[-0.6px]">
                        When can people visit?
                    </h3>

                    <p
                        class="mb-[26px] text-[12px] leading-[1.7] text-[#8593a8]"
                    >
                        Turn on the days you’re open, then set your hours.
                    </p>

                    <p class="mb-2 text-[11px] font-bold">
                        Weekly schedule <RequiredMark />
                    </p>

                    <div
                        v-for="day in days"
                        :key="day"
                        class="grid min-h-[65px] grid-cols-2 items-center gap-x-4 gap-y-3 border-b border-[#edf1f6] py-4 md:grid-cols-[minmax(0,1fr)_96px_232px]"
                    >
                        <span class="text-xs font-medium capitalize">
                            {{ day }}
                        </span>

                        <button
                            type="button"
                            role="switch"
                            :aria-label="`${day} open`"
                            :aria-checked="!form.hours[day].closed"
                            class="flex w-24 items-center gap-2 justify-self-end rounded-md p-1 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-explorer-blue"
                            @click="
                                form.hours[day].closed = !form.hours[day].closed
                            "
                        >
                            <span
                                :class="[
                                    'relative h-5 w-9 rounded-full transition-colors motion-reduce:transition-none',
                                    form.hours[day].closed
                                        ? 'bg-slate-300'
                                        : 'bg-explorer-blue',
                                ]"
                                aria-hidden="true"
                            >
                                <span
                                    :class="[
                                        'absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform motion-reduce:transition-none',
                                        !form.hours[day].closed &&
                                            'translate-x-4',
                                    ]"
                                />
                            </span>
                            <span class="w-10 text-left" aria-hidden="true">
                                {{ form.hours[day].closed ? 'Closed' : 'Open' }}
                            </span>
                        </button>

                        <div
                            class="col-span-2 flex min-w-0 items-center gap-2 md:col-span-1"
                            v-if="!form.hours[day].closed"
                        >
                            <input
                                class="min-w-0 flex-1 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-2 text-[11px] font-normal text-explorer-ink"
                                v-model="form.hours[day].open"
                                type="time"
                                :aria-label="`${day} opening time`"
                            />

                            <span>–</span>

                            <input
                                class="min-w-0 flex-1 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-2 text-[11px] font-normal text-explorer-ink"
                                v-model="form.hours[day].close"
                                type="time"
                                :aria-label="`${day} closing time`"
                            />
                        </div>
                        <span
                            v-else
                            class="col-span-2 text-xs text-slate-400 md:col-span-1"
                        >
                            Closed for the day
                        </span>
                    </div>

                    <p
                        v-for="(message, key) in form.errors"
                        :key="key"
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ message }}
                    </p>
                </div>

                <!-- Step 4: Address and manually chosen location -->
                <div v-else>
                    <span
                        class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                    >
                        04 / RIGHT HERE
                    </span>

                    <h3 class="mx-0 my-[9px] text-[21px] tracking-[-0.6px]">
                        Put yourself on the map
                    </h3>

                    <p
                        class="mb-[26px] text-[12px] leading-[1.7] text-[#8593a8]"
                    >
                        Choose an address suggestion, then place the pin exactly
                        where visitors should go.
                    </p>

                    <StreetAddressInput
                        v-model="form.address"
                        :suggestions="suggestions"
                        :loading="suggestionsLoading"
                        :error="suggestionError"
                        :searched="searched"
                        @search="address.requestSuggestions"
                        @select="handleSelectAddress"
                    />

                    <p
                        v-if="matchingLocation"
                        class="mt-4 text-xs text-slate-500"
                        role="status"
                    >
                        Filling your region, city, and barangay…
                    </p>

                    <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-5">
                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                        >
                            Region
                            <select
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                                v-model="form.region_code"
                                :disabled="matchingLocation"
                            >
                                <option value="">Choose region</option>

                                <option
                                    v-for="item in regions"
                                    :key="item.code"
                                    :value="item.code"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </label>

                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                            v-if="provinces.length"
                        >
                            Province
                            <select
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                                v-model="form.province_code"
                                :disabled="matchingLocation"
                            >
                                <option value="">All / independent city</option>

                                <option
                                    v-for="item in provinces"
                                    :key="item.code"
                                    :value="item.code"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </label>

                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                        >
                            City / municipality
                            <select
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                                v-model="form.city_code"
                                :disabled="matchingLocation"
                            >
                                <option value="">Choose city</option>

                                <option
                                    v-for="item in cities"
                                    :key="item.code"
                                    :value="item.code"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </label>

                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                        >
                            Barangay
                            <select
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-[#f8fafc] p-3 text-[12px] font-[400] text-explorer-ink"
                                v-model="form.barangay_code"
                                :disabled="matchingLocation"
                            >
                                <option value="">Choose barangay</option>

                                <option
                                    v-for="item in barangays"
                                    :key="item.code"
                                    :value="item.code"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <section class="mt-6 space-y-3" aria-label="Business pin">
                        <p
                            v-if="addressError"
                            class="rounded-[8px] bg-[#fff6e6] p-[10px] text-[11px] leading-[1.6] text-[#997432]"
                            role="status"
                        >
                            {{ addressError }}
                        </p>

                        <p
                            v-if="locationError && !saved"
                            class="rounded-[8px] bg-[#fff6e6] p-[10px] text-[11px] leading-[1.6] text-[#997432]"
                            role="status"
                        >
                            {{ locationError }} You can place the business pin
                            manually.
                        </p>

                        <LocationPicker
                            v-if="positionReady"
                            :lat="Number(form.lat)"
                            :lng="Number(form.lng)"
                            @change="handlePlacePin"
                        />

                        <p
                            v-else
                            class="flex h-60 items-center justify-center rounded-xl bg-slate-50 text-xs text-slate-500"
                            role="status"
                        >
                            Finding your location…
                        </p>
                    </section>

                    <div class="mt-5 grid grid-cols-2 gap-4">
                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                        >
                            <span>Latitude <RequiredMark /></span>
                            <input
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
                                v-model.number="form.lat"
                                type="number"
                                min="-90"
                                max="90"
                                step="any"
                                required
                                @input="handleCoordinateInput"
                            />
                        </label>

                        <label
                            class="flex flex-col gap-2 text-[11px] font-[700]"
                        >
                            <span>Longitude <RequiredMark /></span>
                            <input
                                class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
                                v-model.number="form.lng"
                                type="number"
                                min="-180"
                                max="180"
                                step="any"
                                required
                                @input="handleCoordinateInput"
                            />
                        </label>
                    </div>

                    <p
                        v-for="(message, key) in form.errors"
                        :key="key"
                        class="mx-0 mt-[5px] mb-0 text-[11px] text-[#d14a5b] empty:hidden"
                    >
                        {{ message }}
                    </p>
                </div>

                <!-- Delete confirmation -->
                <section
                    v-if="confirming"
                    class="mt-[25px] rounded-[12px] bg-[#fff2f3] p-[18px]"
                    role="alert"
                >
                    <h3 class="m-0 text-[14px]">Delete this listing?</h3>

                    <p class="text-[12px]">
                        You can restore it from Trash later.
                    </p>

                    <p
                        v-if="actionError"
                        class="mx-0 mt-[5px] mb-0 text-[12px] text-[#d14a5b] empty:hidden"
                    >
                        {{ actionError }}
                    </p>

                    <button
                        type="button"
                        class="mt-[10px] mr-2 inline-flex items-center justify-center gap-2 rounded-[10px] border-0 bg-[#dc3545] px-[17px] py-3 text-[12px] font-[700] text-white"
                        :disabled="deleting"
                        @click="handleDelete"
                    >
                        {{ deleting ? 'Deleting…' : 'Delete listing' }}
                    </button>

                    <button
                        type="button"
                        class="mt-[10px] mr-2 inline-flex items-center justify-center gap-2 rounded-[10px] border-0 bg-[#edf2f9] px-[17px] py-3 text-[12px] font-[700] text-[#56708e]"
                        :disabled="deleting"
                        @click="confirming = false"
                    >
                        Keep listing
                    </button>
                </section>
            </div>

            <!-- Step navigation and save action -->
            <footer
                :class="[
                    'flex items-center justify-between gap-[15px] border-t border-[#edf1f6] px-[30px] py-5',
                    'max-md:px-5 max-md:py-4 max-md:pb-[max(16px,_env(safe-area-inset-bottom))]',
                ]"
            >
                <button
                    v-if="step > 0"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-[10px] border-0 bg-[#edf2f9] px-[17px] py-3 text-[12px] font-[700] text-[#56708e]"
                    @click="step--"
                >
                    <ArrowLeft :size="16" />
                    Back
                </button>

                <button
                    v-else-if="saved"
                    type="button"
                    class="flex items-center gap-[6px] border-0 bg-transparent text-[11px] text-[#d14a5b]"
                    @click="confirming = !confirming"
                >
                    <Trash2 :size="16" />
                    Delete listing
                </button>

                <span v-else class="text-[11px] text-[#8a9bb1]">
                    Step {{ step + 1 }} of 4
                </span>

                <button
                    class="inline-flex items-center justify-center gap-2 rounded-[10px] border-0 bg-explorer-blue px-[17px] py-3 text-[12px] font-[700] text-white"
                    :disabled="
                        form.processing ||
                        deleting ||
                        matchingLocation ||
                        (step === 3 && !positionReady)
                    "
                >
                    {{
                        form.processing
                            ? 'Saving…'
                            : step === 3
                              ? saved
                                  ? 'Save changes'
                                  : 'Create listing'
                              : 'Continue'
                    }}
                    <ArrowRight :size="16" />
                </button>
            </footer>
        </form>
    </dialog>
</template>
