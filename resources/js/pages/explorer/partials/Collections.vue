<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUp,
    ArrowDown,
    ListOrdered,
    Check,
    ChevronLeft,
    ChevronRight,
    FolderHeart,
    Globe2,
    Share2,
    LockKeyhole,
    MapPin,
    Pencil,
    Plus,
    Store,
    Trash2,
    X,
} from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import BusinessResults from '@/components/businesses/BusinessResults.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import CollectionSharing from '@/pages/explorer/partials/CollectionSharing.vue';
import {
    index,
    store,
    show,
    update,
    destroy,
} from '@/routes/consumer/collections';
import {
    index as available,
    store as addPlace,
    destroy as removePlace,
    update as movePlace,
} from '@/routes/consumer/collections/places';
import type {
    CollectionsPage,
    CollectionBrowseContext,
    CollectionPlacesPage,
    CollectionPlace,
    PlaceCollection,
    SavedPlaceChange,
    SearchBusiness,
} from '@/types';
import { photoUrl } from '@/utils/businesses';
import { latestRequest } from '@/utils/requests';

const emit = defineEmits<{
    select: [place: SearchBusiness];
    change: [
        places: SearchBusiness[] | null,
        context?: CollectionBrowseContext,
    ];
    showMap: [places: SearchBusiness[]];
}>();

const page = usePage();
const collections = ref<CollectionsPage | null>(null);
const selected = ref<PlaceCollection | null>(null);
const places = ref<CollectionPlacesPage | null>(null);
const picker = ref<CollectionPlacesPage | null>(null);
const loading = ref(false);
const pickerLoading = ref(false);
const busy = ref(false);
const error = ref('');
const pickerError = ref('');
const mutationError = ref('');
const notice = ref('');
const nameOpen = ref(false);
const deleteOpen = ref(false);
const pickerOpen = ref(false);
const sharingOpen = ref(false);
const heading = ref<HTMLElement>();
const form = useForm({ name: '', description: '' });
const arranging = ref(false);
const requests = latestRequest();
const pickerRequests = latestRequest();
const moveRequests = latestRequest();
let editing = false;
let dialogTrigger: HTMLElement | null = null;
let cancelMutation: (() => void) | undefined;
let active = true;
let currentPage = 1;
let pickerPage = 1;

// Separate requests keep a late picker response from replacing the collection view.
async function load(target = 1) {
    const request = requests.start();
    const collection = selected.value;
    loading.value = true;
    error.value = '';
    currentPage = target;

    try {
        const url = collection
            ? show.url(collection.id, { query: { page: target } })
            : index.url({ query: { page: target } });
        const response = await fetch(url, {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Collections unavailable');
        }

        const result = (await response.json()) as
            | CollectionsPage
            | CollectionPlacesPage;

        if (!request.isCurrent()) {
            return;
        }

        currentPage = result.meta.current_page;

        if (collection) {
            applyPlaces(result as CollectionPlacesPage, collection);

            return places.value;
        } else {
            collections.value = result as CollectionsPage;
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load collections. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

function applyPlaces(
    result: CollectionPlacesPage,
    collection: PlaceCollection,
) {
    places.value = result;
    currentPage = result.meta.current_page;
    selected.value = {
        ...collection,
        ...result.collection,
        places_count: result.meta.total,
    };
    emit('change', result.data, {
        id: selected.value.id,
        name: selected.value.name,
        pagination: result.meta,
    });
}

// The explorer can cross a page boundary without duplicating this loader.
function cancelLoad() {
    requests.cancel();
    loading.value = false;
    currentPage = places.value?.meta.current_page ?? 1;
}

defineExpose({ loadPage: load, cancelLoad });

function handleOpen(collection: PlaceCollection) {
    selected.value = collection;
    arranging.value = false;
    places.value = null;
    notice.value = '';
    emit('change', []);
    void load();
    void nextTick(() => heading.value?.focus({ preventScroll: true }));
}

function handleBack() {
    selected.value = null;
    places.value = null;
    emit('change', null);
    void load();
    void nextTick(() => heading.value?.focus({ preventScroll: true }));
}

function handleName(event: MouseEvent, rename = false) {
    dialogTrigger = event.currentTarget as HTMLElement;
    editing = rename;
    form.reset();
    form.clearErrors();
    form.name = rename ? (selected.value?.name ?? '') : '';
    form.description = rename ? (selected.value?.description ?? '') : '';
    mutationError.value = '';
    nameOpen.value = true;
}

function handleSharing(event: MouseEvent) {
    dialogTrigger = event.currentTarget as HTMLElement;
    sharingOpen.value = true;
}

function handleSharingUpdated() {
    void load(currentPage);
}

function handleFailure() {
    mutationError.value = 'Could not save your changes. Please try again.';

    return false;
}

function handleSaveName() {
    const collection = selected.value;
    const options = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            if (!active) {
                return;
            }

            nameOpen.value = false;
            notice.value = editing
                ? 'Collection updated.'
                : 'Collection created.';
            void load(editing ? currentPage : 1);
        },
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
    };

    if (editing && collection) {
        form.put(update.url(collection.id), options);
    } else {
        form.post(store.url(), options);
    }
}

function placeNumber(place: SearchBusiness) {
    if (!places.value) {
        return 0;
    }

    return (
        (places.value.meta.current_page - 1) * places.value.meta.per_page +
        places.value.data.findIndex((member) => member.id === place.id) +
        1
    );
}

async function handleMove(place: CollectionPlace, direction: 'up' | 'down') {
    const collection = selected.value;

    if (!collection || busy.value || loading.value) {
        return;
    }

    const request = moveRequests.start();
    busy.value = true;
    mutationError.value = '';
    notice.value = '';

    try {
        const token = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length);
        const response = await fetch(
            movePlace.url([collection.id, place.saved_id]),
            {
                method: 'PUT',
                credentials: 'same-origin',
                signal: request.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token ? decodeURIComponent(token) : '',
                },
                body: JSON.stringify({ direction, page: currentPage }),
            },
        );

        if (!response.ok) {
            throw new Error('Could not reorder collection');
        }

        const result = (await response.json()) as CollectionPlacesPage;

        if (!request.isCurrent() || selected.value?.id !== collection.id) {
            return;
        }

        applyPlaces(result, collection);
    } catch {
        if (request.isCurrent()) {
            mutationError.value =
                'Could not move this place. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            busy.value = false;
        }
    }
}

function handleAskDelete(event: MouseEvent) {
    dialogTrigger = event.currentTarget as HTMLElement;
    mutationError.value = '';
    deleteOpen.value = true;
}

function handleDelete() {
    if (!selected.value || busy.value) {
        return;
    }

    busy.value = true;
    mutationError.value = '';
    router.delete(destroy.url(selected.value.id), {
        preserveState: true,
        preserveScroll: true,
        onCancelToken: (token) => {
            cancelMutation = () => token.cancel();
        },
        onSuccess: () => {
            if (!active) {
                return;
            }

            deleteOpen.value = false;
            notice.value =
                'Collection deleted. Your saved places are still available.';
            handleBack();
        },
        onError: handleFailure,
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
        onFinish: () => {
            busy.value = false;
        },
    });
}

async function loadPicker(target = 1) {
    if (!selected.value) {
        return;
    }

    const request = pickerRequests.start();
    pickerLoading.value = true;
    pickerError.value = '';
    pickerPage = target;

    try {
        const response = await fetch(
            available.url(selected.value.id, { query: { page: target } }),
            {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('Saved places unavailable');
        }

        const result = (await response.json()) as CollectionPlacesPage;

        if (request.isCurrent()) {
            picker.value = result;
            pickerPage = result.meta.current_page;
        }
    } catch {
        if (request.isCurrent()) {
            pickerError.value =
                'Could not load your saved places. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            pickerLoading.value = false;
        }
    }
}

function handlePicker(event: MouseEvent) {
    dialogTrigger = event.currentTarget as HTMLElement;
    mutationError.value = '';
    picker.value = null;
    pickerOpen.value = true;
    void loadPicker();
}

function handleMembership(place: CollectionPlace, include: boolean) {
    if (!selected.value || busy.value) {
        return;
    }

    busy.value = true;
    mutationError.value = '';
    const args = {
        collection: selected.value.id,
        savedBusiness: place.saved_id,
    };
    const options = {
        preserveState: true,
        preserveScroll: true,
        onCancelToken: (token: { cancel: () => void }) => {
            cancelMutation = () => token.cancel();
        },
        onSuccess: () => {
            if (!active) {
                return;
            }

            void load(currentPage);

            if (pickerOpen.value) {
                void loadPicker(pickerPage);
            } else {
                void nextTick(() =>
                    heading.value?.focus({ preventScroll: true }),
                );
            }
        },
        onError: handleFailure,
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
        onFinish: () => {
            busy.value = false;
        },
    };

    if (include) {
        router.post(addPlace.url(args), {}, options);
    } else {
        router.delete(removePlace.url(args), options);
    }
}

function handleSelect(id: number) {
    const place = places.value?.data.find((place) => place.id === id);

    if (place) {
        emit('select', place);
    }
}

function handleSavedChange(event: Event) {
    const change = (event as CustomEvent<SavedPlaceChange>).detail;

    if (change.userId === page.props.auth?.user?.id) {
        void load(currentPage);

        if (pickerOpen.value) {
            void loadPicker(pickerPage);
        }
    }
}

function handleRestoreFocus(event: Event) {
    event.preventDefault();
    const target = dialogTrigger?.isConnected ? dialogTrigger : heading.value;
    target?.focus({ preventScroll: true });
}

onMounted(() => {
    void load();
    window.addEventListener('saved-place-changed', handleSavedChange);
});

onUnmounted(() => {
    active = false;
    requests.cancel();
    pickerRequests.cancel();
    moveRequests.cancel();
    cancelMutation?.();
    form.cancel();
    window.removeEventListener('saved-place-changed', handleSavedChange);
    emit('change', null);
});
</script>

<template>
    <section aria-label="Your collections" :aria-busy="loading">
        <header class="px-5 pb-4">
            <button
                v-if="selected"
                type="button"
                :disabled="busy"
                class="mb-4 flex h-9 items-center gap-2 text-[11px] text-slate-500 hover:text-explorer-blue"
                @click="handleBack"
            >
                <ArrowLeft :size="14" /> All collections
            </button>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h2
                        ref="heading"
                        tabindex="-1"
                        class="text-[13px] font-semibold break-words"
                    >
                        {{ selected?.name ?? 'Your collections' }}
                    </h2>
                    <p
                        v-if="!selected"
                        class="mt-1.5 text-[11px] leading-5 text-slate-500"
                    >
                        Group your saved places and share your favorites.
                    </p>

                    <div v-else class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            v-if="selected"
                            class="inline-flex h-5 shrink-0 items-center gap-1 rounded-md bg-slate-100 px-1.5 text-[9px] leading-none font-medium text-slate-500"
                        >
                            <component
                                :is="selected?.is_public ? Globe2 : LockKeyhole"
                                :size="10"
                                class="shrink-0"
                                aria-hidden="true"
                            />
                            {{
                                selected?.is_public ? 'Public link' : 'Private'
                            }}
                        </span>

                        <span
                            v-if="selected"
                            class="text-[10px] text-slate-400"
                        >
                            {{ selected.places_count }} saved
                            {{
                                selected.places_count === 1 ? 'place' : 'places'
                            }}
                        </span>
                    </div>
                </div>
                <button
                    v-if="!selected"
                    type="button"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl bg-explorer-blue px-3 text-[11px] font-semibold text-white hover:bg-blue-700"
                    @click="handleName($event)"
                >
                    <Plus :size="14" /> New
                </button>
                <div v-else class="flex shrink-0 gap-1">
                    <button
                        type="button"
                        :disabled="busy"
                        aria-label="Sharing options"
                        title="Sharing options"
                        class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-blue-50 hover:text-explorer-blue"
                        @click="handleSharing"
                    >
                        <Share2 :size="14" />
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        aria-label="Edit collection"
                        title="Edit collection"
                        class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-blue-50 hover:text-explorer-blue"
                        @click="handleName($event, true)"
                    >
                        <Pencil :size="14" />
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        aria-label="Delete collection"
                        title="Delete collection"
                        class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-red-50 hover:text-red-600"
                        @click="handleAskDelete"
                    >
                        <Trash2 :size="14" />
                    </button>
                </div>
            </div>
            <div v-if="selected" class="mt-4 flex gap-2">
                <button
                    type="button"
                    :disabled="busy"
                    class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl bg-explorer-blue px-3 text-[11px] font-semibold text-white hover:bg-blue-700"
                    @click="handlePicker"
                >
                    <Plus :size="14" /> Add places
                </button>
                <button
                    type="button"
                    :disabled="loading || !places?.data.length"
                    class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl border border-explorer-line text-[11px] font-semibold text-explorer-blue hover:bg-blue-50 disabled:opacity-50"
                    @click="emit('showMap', places!.data)"
                >
                    <MapPin :size="14" /> Show on map
                </button>
            </div>
            <p
                v-if="selected"
                class="mt-3 text-[11px] leading-6 whitespace-pre-line text-slate-500"
            >
                {{
                    selected.description ||
                    'Choose a place to explore this collection one stop at a time.'
                }}
            </p>
            <p
                v-if="notice"
                role="status"
                class="mt-3 text-[11px] leading-5 text-explorer-green"
            >
                {{ notice }}
            </p>
        </header>

        <div
            v-if="error"
            role="alert"
            class="mx-5 mb-4 rounded-xl bg-red-50 p-3 text-[11px] text-red-600"
        >
            {{ error }}
            <button
                type="button"
                class="ml-1 h-9 underline"
                @click="load(currentPage)"
            >
                Retry
            </button>
        </div>
        <p
            v-if="loading"
            role="status"
            class="px-5 pb-4 text-[11px] text-slate-500"
        >
            Loading {{ selected ? 'places' : 'collections' }}...
        </p>
        <p
            v-if="mutationError && !nameOpen && !deleteOpen && !pickerOpen"
            role="alert"
            class="mx-5 mb-4 text-[11px] text-red-600"
        >
            {{ mutationError }}
        </p>

        <div
            v-if="selected && places?.data.length"
            class="mx-5 mb-4 flex items-center justify-between gap-2"
        >
            <span class="text-[10px] text-slate-400">{{
                arranging
                    ? 'Move places in your preferred order.'
                    : 'Your places, in your order.'
            }}</span>
            <button
                type="button"
                :aria-pressed="arranging"
                :disabled="busy || loading"
                class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-explorer-line px-3 text-[11px] font-semibold text-explorer-blue disabled:opacity-40"
                @click="arranging = !arranging"
            >
                <ListOrdered :size="14" />{{ arranging ? 'Done' : 'Reorder' }}
            </button>
        </div>

        <!-- Each collection contains a bounded page of the consumer's saves. -->
        <template v-if="!selected">
            <div class="space-y-2 px-5">
                <button
                    v-for="collection in collections?.data"
                    :key="collection.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border border-explorer-line p-3 text-left hover:border-blue-200 hover:bg-blue-50/40"
                    @click="handleOpen(collection)"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-explorer-blue"
                        ><FolderHeart :size="19"
                    /></span>
                    <span class="min-w-0 flex-1"
                        ><span class="block truncate text-xs font-semibold">{{
                            collection.name
                        }}</span
                        ><span class="mt-1 block text-[10px] text-slate-500"
                            >{{ collection.places_count }}
                            {{
                                collection.places_count === 1
                                    ? 'place'
                                    : 'places'
                            }}</span
                        ></span
                    ><component
                        :is="collection.is_public ? Globe2 : LockKeyhole"
                        :size="13"
                        class="shrink-0 text-slate-400"
                    />
                </button>
            </div>
            <div
                v-if="!loading && !error && !collections?.data.length"
                class="mx-5 rounded-xl border border-dashed border-explorer-line px-5 py-10 text-center"
            >
                <FolderHeart
                    :size="28"
                    class="mx-auto mb-3 text-explorer-blue"
                />
                <h3 class="text-[13px] font-semibold">
                    A list for every little plan
                </h3>
                <p class="mt-2 text-[11px] leading-6 text-slate-500">
                    Create “Kape spots” or “Weekend food trip”, then add your
                    saved places.
                </p>
            </div>
            <nav
                v-if="collections && collections.meta.last_page > 1"
                aria-label="Collection pages"
                class="mx-5 mt-4 flex items-center justify-between gap-3 border-t border-explorer-line pt-4"
            >
                <button
                    type="button"
                    :disabled="loading || collections.meta.current_page === 1"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] disabled:opacity-40"
                    @click="load(currentPage - 1)"
                >
                    <ChevronLeft :size="14" /> Previous</button
                ><span class="text-[10px] text-slate-400"
                    >{{ currentPage }} / {{ collections.meta.last_page }}</span
                ><button
                    type="button"
                    :disabled="
                        loading || currentPage === collections.meta.last_page
                    "
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] disabled:opacity-40"
                    @click="load(currentPage + 1)"
                >
                    Next <ChevronRight :size="14" />
                </button>
            </nav>
        </template>
        <BusinessResults
            v-else-if="places?.data.length"
            :places="places.data"
            :pagination="places.meta"
            :loading="loading"
            @select="handleSelect"
            @page-change="load($event)"
        >
            <template #footer="{ place }">
                <div
                    v-if="arranging"
                    class="flex items-center justify-between gap-2 px-1 pt-1.5"
                >
                    <span class="text-[10px] text-slate-400"
                        >Place {{ placeNumber(place) }}</span
                    >
                    <div class="flex gap-1">
                        <button
                            type="button"
                            :aria-label="`Move ${place.name} up`"
                            :disabled="
                                busy || loading || placeNumber(place) === 1
                            "
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-2 text-[10px] text-explorer-blue disabled:opacity-40"
                            @click="handleMove(place as CollectionPlace, 'up')"
                        >
                            <ArrowUp :size="13" /> Move up
                        </button>
                        <button
                            type="button"
                            :aria-label="`Move ${place.name} down`"
                            :disabled="
                                busy ||
                                loading ||
                                placeNumber(place) === places.meta.total
                            "
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-2 text-[10px] text-explorer-blue disabled:opacity-40"
                            @click="
                                handleMove(place as CollectionPlace, 'down')
                            "
                        >
                            <ArrowDown :size="13" /> Move down
                        </button>
                    </div>
                </div>
            </template>
            <template #action="{ place }"
                ><button
                    type="button"
                    :disabled="busy"
                    :aria-label="`Remove ${place.name} from collection`"
                    title="Remove from collection"
                    class="grid size-9 place-items-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                    @click="handleMembership(place as CollectionPlace, false)"
                >
                    <X :size="15" /></button
            ></template>
        </BusinessResults>
        <div
            v-else-if="selected && !loading && !error"
            class="mx-5 rounded-xl border border-dashed border-explorer-line px-5 py-10 text-center"
        >
            <MapPin :size="28" class="mx-auto mb-3 text-explorer-blue" />
            <h3 class="text-[13px] font-semibold">Start with a saved place</h3>
            <p class="mt-2 text-[11px] leading-6 text-slate-500">
                Add businesses from your saved list to build this collection.
            </p>
        </div>
    </section>

    <CollectionSharing
        v-if="selected"
        v-model:open="sharingOpen"
        :collection="selected"
        @updated="handleSharingUpdated"
        @close-auto-focus="handleRestoreFocus"
    />

    <!-- Small, accessible dialogs keep collection work inside the explorer. -->
    <Dialog v-model:open="nameOpen">
        <DialogContent
            class="rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-sm"
            @close-auto-focus="handleRestoreFocus"
        >
            <DialogTitle class="font-explorer-heading text-base">{{
                editing ? 'Edit collection' : 'New collection'
            }}</DialogTitle>
            <DialogDescription class="text-xs leading-6 text-slate-500">{{
                editing
                    ? 'Update the name and description shown for this collection.'
                    : 'Give your saved places a little plan. New collections are private.'
            }}</DialogDescription>
            <form class="space-y-4" @submit.prevent="handleSaveName">
                <label for="collection-name" class="block text-xs font-semibold"
                    >Collection name <span class="text-red-500">*</span></label
                >
                <input
                    id="collection-name"
                    v-model="form.name"
                    :aria-invalid="!!form.errors.name"
                    aria-describedby="collection-name-error"
                    required
                    maxlength="80"
                    placeholder="e.g. Kape spots"
                    class="h-10 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs outline-none focus:border-explorer-blue focus:ring-2 focus:ring-blue-100"
                />
                <p
                    v-if="form.errors.name || mutationError"
                    id="collection-name-error"
                    role="alert"
                    class="text-[11px] text-red-600"
                >
                    {{ form.errors.name || mutationError }}
                </p>
                <label
                    for="collection-description"
                    class="block text-xs font-semibold"
                    >Description
                    <span class="font-normal text-slate-400"
                        >Optional</span
                    ></label
                >
                <textarea
                    id="collection-description"
                    v-model="form.description"
                    maxlength="500"
                    rows="3"
                    placeholder="What makes these places worth exploring?"
                    :aria-invalid="!!form.errors.description"
                    aria-describedby="collection-description-error"
                    class="w-full resize-none rounded-xl border border-explorer-line bg-explorer-cloud px-3 py-2.5 text-xs leading-6 outline-none focus:border-explorer-blue focus:ring-2 focus:ring-blue-100"
                />
                <p
                    v-if="form.errors.description"
                    id="collection-description-error"
                    role="alert"
                    class="text-[11px] text-red-600"
                >
                    {{ form.errors.description }}
                </p>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="h-9 w-full rounded-xl bg-explorer-blue text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Saving...'
                            : editing
                              ? 'Save changes'
                              : 'Create collection'
                    }}
                </button>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent
            class="rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-sm"
            @close-auto-focus="handleRestoreFocus"
        >
            <DialogTitle class="font-explorer-heading text-base"
                >Delete {{ selected?.name }}?</DialogTitle
            >
            <DialogDescription class="text-xs leading-6 text-slate-500"
                >This removes the collection. Your saved places will stay in All
                saved.</DialogDescription
            >
            <p v-if="mutationError" role="alert" class="text-xs text-red-600">
                {{ mutationError }}
            </p>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    :disabled="busy"
                    class="h-9 rounded-xl border border-explorer-line px-4 text-xs"
                    @click="deleteOpen = false"
                >
                    Cancel</button
                ><button
                    type="button"
                    :disabled="busy"
                    class="h-9 rounded-xl bg-red-600 px-4 text-xs font-semibold text-white disabled:opacity-50"
                    @click="handleDelete"
                >
                    {{ busy ? 'Deleting...' : 'Delete collection' }}
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog
        v-model:open="pickerOpen"
        @update:open="!$event && pickerRequests.cancel()"
    >
        <DialogContent
            class="flex max-h-[85dvh] flex-col rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-md"
            @close-auto-focus="handleRestoreFocus"
        >
            <DialogTitle class="pr-5 font-explorer-heading text-base"
                >Add to {{ selected?.name }}</DialogTitle
            >
            <DialogDescription class="text-xs leading-6 text-slate-500"
                >Choose from your saved places. Adding or removing here keeps
                the original bookmark.</DialogDescription
            >
            <div class="min-h-0 space-y-2 overflow-y-auto overscroll-contain">
                <p
                    v-if="pickerLoading"
                    role="status"
                    class="py-3 text-xs text-slate-500"
                >
                    Loading saved places...
                </p>
                <p v-if="pickerError" role="alert" class="text-xs text-red-600">
                    {{ pickerError }}
                    <button
                        type="button"
                        class="h-9 underline"
                        @click="loadPicker(pickerPage)"
                    >
                        Retry
                    </button>
                </p>
                <p
                    v-if="mutationError"
                    role="alert"
                    class="text-xs text-red-600"
                >
                    {{ mutationError }}
                </p>
                <article
                    v-for="place in picker?.data"
                    :key="place.saved_id"
                    class="flex items-center gap-3 rounded-xl border border-explorer-line p-2.5"
                >
                    <span
                        class="relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-blue-50 text-blue-300"
                        ><Store :size="19" /><img
                            v-if="photoUrl(place.logo)"
                            :src="photoUrl(place.logo)"
                            alt=""
                            class="absolute inset-0 size-full object-cover"
                            @error="
                                ($event.target as HTMLImageElement).hidden =
                                    true
                            "
                    /></span>
                    <span class="min-w-0 flex-1"
                        ><span class="block truncate text-xs font-semibold">{{
                            place.name
                        }}</span
                        ><span
                            class="mt-1 block truncate text-[10px] text-slate-500"
                            >{{ place.category?.name }}</span
                        ></span
                    >
                    <button
                        type="button"
                        :disabled="busy || pickerLoading"
                        :aria-pressed="place.in_collection"
                        :aria-label="`${place.in_collection ? 'Remove' : 'Add'} ${place.name} ${place.in_collection ? 'from' : 'to'} collection`"
                        :class="[
                            'inline-flex h-9 shrink-0 items-center gap-1 rounded-lg px-3 text-[11px] font-semibold',
                            place.in_collection
                                ? 'bg-blue-50 text-explorer-blue'
                                : 'border border-explorer-line text-slate-500 hover:bg-slate-50',
                        ]"
                        @click="handleMembership(place, !place.in_collection)"
                    >
                        <Check v-if="place.in_collection" :size="13" /><Plus
                            v-else
                            :size="13"
                        />{{ place.in_collection ? 'Added' : 'Add' }}
                    </button>
                </article>
                <p
                    v-if="
                        !pickerLoading && !pickerError && !picker?.data.length
                    "
                    class="py-6 text-center text-xs leading-6 text-slate-500"
                >
                    No saved places yet. Bookmark a business first, then add it
                    here.
                </p>
            </div>
            <nav
                v-if="picker && picker.meta.last_page > 1"
                aria-label="Saved place picker pages"
                class="flex items-center justify-between gap-3 border-t border-explorer-line pt-3"
            >
                <button
                    type="button"
                    :disabled="pickerLoading || busy || pickerPage === 1"
                    class="flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] disabled:opacity-40"
                    @click="loadPicker(pickerPage - 1)"
                >
                    <ChevronLeft :size="14" /> Previous</button
                ><span class="text-[10px] text-slate-400"
                    >{{ pickerPage }} / {{ picker.meta.last_page }}</span
                ><button
                    type="button"
                    :disabled="
                        pickerLoading ||
                        busy ||
                        pickerPage === picker.meta.last_page
                    "
                    class="flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-3 text-[11px] disabled:opacity-40"
                    @click="loadPicker(pickerPage + 1)"
                >
                    Next <ChevronRight :size="14" />
                </button>
            </nav>
            <button
                type="button"
                class="h-9 shrink-0 rounded-xl bg-explorer-blue text-xs font-semibold text-white hover:bg-blue-700"
                @click="pickerOpen = false"
            >
                Done
            </button>
        </DialogContent>
    </Dialog>
</template>
