<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Package, Pencil, Plus, Trash2, ArrowLeft } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import OfferingForm from '@/pages/explorer/partials/listings/OfferingForm.vue';
import { destroy, index } from '@/routes/owner/businesses/offerings';
import type {
    BusinessOffering,
    BusinessOfferingDraft,
    OfferingPage,
} from '@/types';
import { offeringPrice } from '@/utils/businesses';
import { latestRequest } from '@/utils/requests';

const props = defineProps<{
    businessId: number | null;
    currencies: string[];
}>();
const drafts = defineModel<BusinessOfferingDraft[]>({ required: true });
const emit = defineEmits<{
    busy: [value: boolean];
    editing: [value: boolean];
}>();
const businessId = props.businessId;

const catalog = ref<OfferingPage | null>(null);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const error = ref('');
const editorOpen = ref(false);
const editing = ref<BusinessOffering | BusinessOfferingDraft | null>(null);
const removing = ref<BusinessOffering | null>(null);
const addButton = ref<HTMLButtonElement>();
const editorHeading = ref<HTMLElement>();
const requests = latestRequest();
let trigger: HTMLElement | null = null;
let requestedPage = 1;

async function load(page = 1) {
    if (businessId === null) {
        return;
    }

    const request = requests.start();
    requestedPage = page;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(
            index.url(businessId, { query: { page } }),
            {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('Catalog unavailable');
        }

        const result = (await response.json()) as OfferingPage;

        if (request.isCurrent()) {
            catalog.value = result;
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load your items. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

function restoreFocus() {
    void nextTick(() =>
        (trigger?.isConnected ? trigger : addButton.value)?.focus({
            preventScroll: true,
        }),
    );
}

function handleEdit(
    item: BusinessOffering | BusinessOfferingDraft | null,
    event: Event,
) {
    trigger = event.currentTarget as HTMLElement;
    editing.value = item;
    editorOpen.value = true;
    void nextTick(() => editorHeading.value?.focus());
}

function handleCancel() {
    editorOpen.value = false;
    restoreFocus();
}

function handleSaved(draft?: BusinessOfferingDraft) {
    if (draft) {
        const position = drafts.value.findIndex((item) => item.id === draft.id);
        const updated = [...drafts.value];

        if (position >= 0) {
            updated[position] = draft;
        } else {
            updated.push(draft);
        }

        drafts.value = updated;
    } else {
        void load(1);
    }

    saving.value = false;
    handleCancel();
}

function handleRemove(item: BusinessOffering, event: Event) {
    trigger = event.currentTarget as HTMLElement;
    removing.value = item;
    error.value = '';
}

function handleDelete() {
    if (!removing.value || deleting.value) {
        return;
    }

    if (businessId === null) {
        drafts.value = drafts.value.filter(
            (item) => item.id !== removing.value?.id,
        );
        removing.value = null;
        restoreFocus();

        return;
    }

    deleting.value = true;
    router.delete(
        destroy.url({ business: businessId, offering: removing.value.id }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                removing.value = null;
                restoreFocus();
                void load(catalog.value?.meta.current_page ?? 1);
            },
            onError: () => {
                error.value = 'Could not remove this item. Please try again.';
            },
            onFinish: () => {
                deleting.value = false;
            },
        },
    );
}

onMounted(() => void load());
onUnmounted(() => requests.cancel());

function retry() {
    return load(requestedPage);
}

const items = computed(() =>
    props.businessId === null ? drafts.value : (catalog.value?.data ?? []),
);

watch([saving, deleting], () => emit('busy', saving.value || deleting.value));
watch([editorOpen, removing], () =>
    emit('editing', editorOpen.value || !!removing.value),
);
</script>

<template>
    <!-- The editor replaces the list inside the existing drawer. -->
    <section v-if="editorOpen" class="space-y-5">
        <button
            type="button"
            :disabled="saving"
            class="inline-flex h-9 items-center gap-2 text-xs text-slate-500"
            @click="handleCancel"
        >
            <ArrowLeft :size="15" /> Back to items
        </button>
        <h4 ref="editorHeading" tabindex="-1" class="text-base font-semibold">
            {{ editing ? 'Edit item' : 'Add product or service' }}
        </h4>
        <OfferingForm
            :business-id="businessId"
            :item="editing"
            :currencies="currencies"
            @saved="handleSaved"
            @cancel="handleCancel"
            @busy="saving = $event"
        />
    </section>
    <section v-else class="space-y-4" aria-label="Products and services">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-slate-500"
                >{{
                    businessId === null
                        ? drafts.length
                        : (catalog?.meta.total ?? 0)
                }}
                items</span
            >
            <button
                ref="addButton"
                type="button"
                :disabled="
                    loading ||
                    deleting ||
                    !!removing ||
                    (businessId === null && drafts.length >= 20)
                "
                class="inline-flex h-9 items-center gap-2 rounded-lg bg-explorer-blue px-3 text-xs font-semibold text-white disabled:opacity-50"
                @click="handleEdit(null, $event)"
            >
                <Plus :size="15" /> Add item
            </button>
        </div>
        <p v-if="loading" role="status" class="py-4 text-xs text-slate-500">
            Loading your items...
        </p>
        <div
            v-if="error"
            role="alert"
            class="rounded-xl bg-red-50 p-3 text-xs text-red-600"
        >
            {{ error }}
            <button
                v-if="!removing"
                type="button"
                class="ml-2 h-9 underline"
                @click="retry"
            >
                Retry
            </button>
        </div>
        <section
            v-if="removing"
            role="alert"
            class="space-y-3 rounded-xl border border-red-100 bg-red-50 p-4"
        >
            <h4 class="text-xs font-semibold">Remove {{ removing.name }}?</h4>
            <p class="text-[11px] leading-5 text-slate-500">
                This item and its photo will be removed from your catalog.
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    :disabled="deleting"
                    class="h-9 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white"
                    @click="handleDelete"
                >
                    {{ deleting ? 'Removing...' : 'Remove item' }}
                </button>
                <button
                    type="button"
                    :disabled="deleting"
                    class="h-9 rounded-lg border border-explorer-line bg-white px-3 text-xs"
                    @click="removing = null"
                >
                    Keep item
                </button>
            </div>
        </section>
        <div
            v-if="items.length"
            class="divide-y divide-explorer-line rounded-xl border border-explorer-line"
        >
            <article
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3 p-4"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-explorer-blue"
                    ><Package :size="18"
                /></span>
                <div class="min-w-0 flex-1">
                    <h4 class="truncate text-xs font-semibold">
                        {{ item.name }}
                    </h4>
                    <p class="mt-1 text-[10px] text-slate-500">
                        {{ item.type === 'product' ? 'Product' : 'Service' }}
                    </p>
                    <p
                        v-if="offeringPrice(item)"
                        class="mt-1 text-[11px] font-semibold text-explorer-blue"
                    >
                        {{ offeringPrice(item) }}
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="deleting || !!removing || loading"
                    :aria-label="`Edit ${item.name}`"
                    class="grid size-9 shrink-0 place-items-center rounded-lg hover:bg-blue-50"
                    @click="handleEdit(item, $event)"
                >
                    <Pencil :size="15" />
                </button>
                <button
                    type="button"
                    :disabled="deleting || !!removing || loading"
                    :aria-label="`Remove ${item.name}`"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600"
                    @click="handleRemove(item, $event)"
                >
                    <Trash2 :size="15" />
                </button>
            </article>
        </div>
        <div
            v-else-if="!loading && !error"
            class="rounded-xl border border-dashed border-blue-200 bg-blue-50/40 px-5 py-8 text-center"
        >
            <Package :size="28" class="mx-auto mb-3 text-explorer-blue" />
            <h4 class="text-xs font-semibold">What do you offer?</h4>
            <p class="mt-2 text-[11px] leading-6 text-slate-500">
                Add a product or service. Photos and prices are optional, and
                you can come back to this later.
            </p>
        </div>
        <p
            v-if="businessId === null && drafts.length"
            class="text-[11px] leading-5 text-slate-500"
        >
            These items will be saved when you create your listing. You can add
            up to 20 now and more later.
        </p>
        <p
            v-else-if="businessId !== null"
            class="text-[11px] leading-5 text-slate-500"
        >
            Item changes are saved immediately. Your other listing details are
            saved with Save changes.
        </p>
        <nav
            v-if="catalog && catalog.meta.last_page > 1"
            class="flex items-center justify-between"
            aria-label="Catalog pages"
        >
            <button
                type="button"
                :disabled="
                    loading || !!removing || catalog.meta.current_page <= 1
                "
                class="h-9 rounded-lg border border-explorer-line px-3 text-xs"
                @click="load(catalog.meta.current_page - 1)"
            >
                Previous
            </button>
            <span class="text-[11px] text-slate-500"
                >{{ catalog.meta.current_page }} /
                {{ catalog.meta.last_page }}</span
            >
            <button
                type="button"
                :disabled="
                    loading ||
                    !!removing ||
                    catalog.meta.current_page >= catalog.meta.last_page
                "
                class="h-9 rounded-lg border border-explorer-line px-3 text-xs"
                @click="load(catalog.meta.current_page + 1)"
            >
                Next
            </button>
        </nav>
    </section>
</template>
