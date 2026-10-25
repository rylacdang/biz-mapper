<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2 } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { store, update } from '@/routes/owner/businesses/offerings';
import type { BusinessOffering, BusinessOfferingDraft } from '@/types';
import { photoUrl } from '@/utils/businesses';

const props = defineProps<{
    businessId: number | null;
    item: BusinessOffering | BusinessOfferingDraft | null;
}>();

const emit = defineEmits<{
    saved: [draft?: BusinessOfferingDraft];
    cancel: [];
    busy: [value: boolean];
}>();

const editor = ref<HTMLElement>();
const fileInput = ref<HTMLInputElement>();

const businessId = props.businessId;
const item = props.item;

const form = useForm({
    type: item?.type ?? 'service',
    name: item?.name ?? '',
    description: item?.description ?? '',
    photo: item && 'file' in item ? item.file : (null as File | null),
    remove_photo: item && 'remove_photo' in item ? item.remove_photo : false,
    price: item?.price ?? '',
    price_max: item?.price_max ?? '',
    price_unit: item?.price_unit ?? '',
    _method: item ? 'PUT' : 'POST',
});
const preview = ref(photoUrl(item?.photo));
const hasPrice = computed(() => form.price !== '');
let objectUrl: string | undefined;

if (form.photo) {
    objectUrl = URL.createObjectURL(form.photo);
    preview.value = objectUrl;
}

function releasePreview() {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
    }

    objectUrl = undefined;
}

function handlePhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    input.value = '';

    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size > 5 * 1024 * 1024
    ) {
        form.setError('photo', 'Choose a JPG, PNG, or WebP photo up to 5 MB.');

        return;
    }

    releasePreview();
    form.clearErrors('photo');
    form.photo = file;
    form.remove_photo = false;
    objectUrl = URL.createObjectURL(file);
    preview.value = objectUrl;
}

function handleRemovePhoto() {
    releasePreview();
    form.photo = null;
    form.remove_photo = true;
    preview.value = undefined;
}

function handleSubmit() {
    if (businessId === null) {
        if (!form.name.trim()) {
            form.setError('name', 'Enter an item name.');

            return;
        }

        if (
            form.price_max !== '' &&
            (form.price === '' || Number(form.price_max) < Number(form.price))
        ) {
            form.setError(
                'price_max',
                'Enter a maximum amount at least equal to the starting price.',
            );

            return;
        }

        emit('saved', {
            id: item?.id ?? Date.now(),
            type: form.type,
            name: form.name.trim(),
            description: form.description || null,
            photo: item?.photo ?? null,
            file: form.photo,
            remove_photo: form.remove_photo,
            price: form.price === '' ? null : String(form.price),
            price_max: form.price_max === '' ? null : String(form.price_max),
            currency: form.price === '' ? null : 'PHP',
            price_unit: form.price_unit || null,
        });

        return;
    }

    const url = item
        ? update.url({ business: businessId, offering: item.id })
        : store.url(businessId);
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}

onUnmounted(() => {
    form.cancel();
    releasePreview();
});

watch(
    () => form.processing,
    (value) => emit('busy', value),
);

function handleSave() {
    const inputs = editor.value?.querySelectorAll<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >('input, select, textarea');

    if (inputs) {
        for (const input of inputs) {
            if (!input.reportValidity()) {
                return;
            }
        }
    }

    handleSubmit();
}
</script>

<template>
    <section ref="editor" class="space-y-5" aria-label="Item editor">
        <fieldset
            :disabled="form.processing"
            class="space-y-5 disabled:opacity-70"
        >
            <!-- Core details -->
            <div>
                <label
                    for="offering-type"
                    class="mb-2 block text-[11px] font-semibold"
                    >Type
                    <span class="text-red-500" aria-hidden="true"
                        >*</span
                    ></label
                >
                <select
                    id="offering-type"
                    v-model="form.type"
                    required
                    class="h-11 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs"
                >
                    <option value="product">Product</option>
                    <option value="service">Service</option>
                </select>
                <p
                    v-if="form.errors.type"
                    class="mt-1 text-[11px] text-red-600"
                >
                    {{ form.errors.type }}
                </p>
            </div>
            <div>
                <label
                    for="offering-name"
                    class="mb-2 block text-[11px] font-semibold"
                    >Name
                    <span class="text-red-500" aria-hidden="true"
                        >*</span
                    ></label
                >
                <input
                    id="offering-name"
                    v-model="form.name"
                    required
                    maxlength="160"
                    placeholder="Enter a product or service name"
                    class="h-11 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs"
                />
                <p
                    v-if="form.errors.name"
                    class="mt-1 text-[11px] text-red-600"
                >
                    {{ form.errors.name }}
                </p>
            </div>
            <div>
                <label
                    for="offering-description"
                    class="mb-2 block text-[11px] font-semibold"
                    >Description
                    <span class="ml-1 font-normal text-slate-400"
                        >Optional</span
                    ></label
                >
                <textarea
                    id="offering-description"
                    v-model="form.description"
                    rows="3"
                    maxlength="2000"
                    placeholder="What is included?"
                    class="w-full resize-y rounded-xl border border-explorer-line bg-explorer-cloud p-3 text-xs"
                />
                <p
                    v-if="form.errors.description"
                    class="mt-1 text-[11px] text-red-600"
                >
                    {{ form.errors.description }}
                </p>
            </div>

            <!-- A file picker is opened only by the explicit photo button. -->
            <div>
                <span class="mb-2 block text-[11px] font-semibold"
                    >Photo
                    <span class="ml-1 font-normal text-slate-400"
                        >Optional</span
                    ></span
                >
                <div
                    class="flex items-center gap-4 rounded-xl border border-explorer-line p-3"
                >
                    <img
                        v-if="preview"
                        :src="preview"
                        alt="Selected item photo"
                        class="size-16 rounded-lg object-cover"
                    />
                    <span
                        v-else
                        class="grid size-16 shrink-0 place-items-center rounded-lg bg-blue-50 text-explorer-blue"
                        ><ImagePlus :size="22"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            hidden
                            aria-label="Choose item photo"
                            @change="handlePhoto"
                        />
                        <button
                            type="button"
                            class="h-9 rounded-lg border border-explorer-line px-3 text-[11px] font-semibold hover:bg-blue-50"
                            @click="fileInput?.click()"
                        >
                            {{ preview ? 'Change photo' : 'Choose photo' }}
                        </button>
                        <p class="mt-2 text-[10px] text-slate-400">
                            JPG, PNG, or WebP · Up to 5 MB
                        </p>
                    </div>
                    <button
                        v-if="preview"
                        type="button"
                        aria-label="Remove item photo"
                        class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600"
                        @click="handleRemovePhoto"
                    >
                        <Trash2 :size="16" />
                    </button>
                </div>
                <p
                    v-if="form.errors.photo || form.errors.remove_photo"
                    class="mt-1 text-[11px] text-red-600"
                >
                    {{ form.errors.photo || form.errors.remove_photo }}
                </p>
            </div>

            <!-- Leaving the price blank publishes no price claim. -->
            <fieldset class="space-y-4 border-t border-explorer-line pt-4">
                <legend class="pr-2 text-xs font-semibold">
                    Price
                    <span class="ml-1 font-normal text-slate-400"
                        >Optional</span
                    >
                </legend>
                <p class="text-[11px] text-slate-500">
                    Leave blank if you do not want to display a price.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label
                            for="offering-price"
                            class="mb-2 block text-[11px] font-semibold"
                            >Amount / from (₱)</label
                        >
                        <input
                            id="offering-price"
                            v-model="form.price"
                            type="number"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            placeholder="Amount"
                            class="h-11 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs"
                        />
                        <p
                            v-if="form.errors.price"
                            class="mt-1 text-[11px] text-red-600"
                        >
                            {{ form.errors.price }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="offering-price-max"
                            class="mb-2 block text-[11px] font-semibold"
                            >Up to (₱)
                            <span class="font-normal text-slate-400"
                                >Optional</span
                            ></label
                        >
                        <input
                            id="offering-price-max"
                            v-model="form.price_max"
                            type="number"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            placeholder="Maximum amount"
                            class="h-11 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs"
                        />
                        <p
                            v-if="form.errors.price_max"
                            class="mt-1 text-[11px] text-red-600"
                        >
                            {{ form.errors.price_max }}
                        </p>
                    </div>
                </div>
                <div v-if="hasPrice">
                    <div>
                        <label
                            for="offering-unit"
                            class="mb-2 block text-[11px] font-semibold"
                            >Per
                            <span class="font-normal text-slate-400"
                                >Optional</span
                            ></label
                        >
                        <input
                            id="offering-unit"
                            v-model="form.price_unit"
                            maxlength="50"
                            placeholder="load, piece, hour..."
                            class="h-11 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs"
                        />
                        <p
                            v-if="form.errors.price_unit"
                            class="mt-1 text-[11px] text-red-600"
                        >
                            {{ form.errors.price_unit }}
                        </p>
                    </div>
                </div>
            </fieldset>
        </fieldset>
        <div
            class="sticky bottom-0 flex justify-end gap-2 border-t border-explorer-line bg-white pt-4 pb-1"
        >
            <button
                type="button"
                :disabled="form.processing"
                class="h-9 rounded-xl border border-explorer-line px-4 text-xs font-semibold"
                @click="emit('cancel')"
            >
                Cancel
            </button>
            <button
                type="button"
                @click="handleSave"
                :disabled="form.processing"
                class="h-9 rounded-xl bg-explorer-blue px-4 text-xs font-semibold text-white disabled:opacity-50"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : item
                          ? 'Save changes'
                          : 'Add item'
                }}
            </button>
        </div>
    </section>
</template>
