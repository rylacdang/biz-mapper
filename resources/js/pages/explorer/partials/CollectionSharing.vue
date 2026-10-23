<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    Copy,
    Globe2,
    LockKeyhole,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { update } from '@/routes/consumer/collections/sharing';
import type { PlaceCollection } from '@/types';

const props = defineProps<{ collection: PlaceCollection }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ updated: []; closeAutoFocus: [event: Event] }>();
const form = useForm({ is_public: props.collection.is_public });
const changed = computed(() => form.is_public !== props.collection.is_public);
const error = ref('');
const message = ref('');
let active = true;

watch([() => open.value, () => props.collection.is_public], () => {
    form.is_public = props.collection.is_public;
    form.clearErrors();
    error.value = '';
    message.value = '';
});

function handleFailure() {
    error.value = 'Could not update sharing. Please try again.';

    return false;
}

function handleSave() {
    error.value = '';
    message.value = '';
    form.put(update.url(props.collection.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            if (active) {
                emit('updated');
            }
        },
        onError: handleFailure,
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
    });
}

async function handleCopy() {
    const url = props.collection.share_url;

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);

        if (active) {
            message.value = 'Link copied.';
        }
    } catch {
        if (active) {
            message.value = 'Select and copy the link below.';
        }
    }
}

onUnmounted(() => {
    active = false;
    form.cancel();
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85dvh] overflow-y-auto rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-md"
            @close-auto-focus="emit('closeAutoFocus', $event)"
        >
            <DialogTitle class="pr-6 font-explorer-heading text-base"
                >Share {{ collection.name }}</DialogTitle
            >
            <DialogDescription class="text-xs leading-6 text-slate-500"
                >Choose who can open this collection. Your account details and
                other saved places stay private.</DialogDescription
            >

            <div
                class="grid grid-cols-2 gap-2"
                aria-label="Collection visibility"
            >
                <button
                    type="button"
                    :disabled="form.processing"
                    :aria-pressed="!form.is_public"
                    :class="[
                        'flex h-12 items-center gap-2 rounded-xl border px-3 text-xs font-semibold',
                        !form.is_public
                            ? 'border-blue-200 bg-blue-50 text-explorer-blue'
                            : 'border-explorer-line text-slate-500',
                    ]"
                    @click="form.is_public = false"
                >
                    <LockKeyhole :size="16" /> Private
                    <Check v-if="!form.is_public" :size="14" class="ml-auto" />
                </button>
                <button
                    type="button"
                    :disabled="form.processing"
                    :aria-pressed="form.is_public"
                    :class="[
                        'flex h-12 items-center gap-2 rounded-xl border px-3 text-xs font-semibold',
                        form.is_public
                            ? 'border-blue-200 bg-blue-50 text-explorer-blue'
                            : 'border-explorer-line text-slate-500',
                    ]"
                    @click="form.is_public = true"
                >
                    <Globe2 :size="16" /> Public link
                    <Check v-if="form.is_public" :size="14" class="ml-auto" />
                </button>
            </div>

            <p
                class="rounded-xl bg-explorer-cloud px-3 py-3 text-[11px] leading-6 text-slate-500"
            >
                {{
                    form.is_public
                        ? 'Anyone with the link can view the collection name and its available businesses. They can browse without logging in.'
                        : 'Only you can view this collection. Making it private disables access through its public link.'
                }}
            </p>
            <p
                v-if="error || form.errors.is_public"
                role="alert"
                class="text-[11px] text-red-600"
            >
                {{ error || form.errors.is_public }}
            </p>
            <button
                v-if="changed"
                type="button"
                :disabled="form.processing"
                class="h-9 rounded-xl bg-explorer-blue text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="handleSave"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : form.is_public
                          ? 'Make public'
                          : 'Make private'
                }}
            </button>

            <div
                v-if="collection.is_public && collection.share_url"
                class="space-y-3 border-t border-explorer-line pt-4"
            >
                <label
                    for="collection-public-link"
                    class="block text-[11px] font-semibold"
                    >Collection link</label
                >
                <input
                    id="collection-public-link"
                    :value="collection.share_url"
                    readonly
                    class="h-10 w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 text-xs outline-none focus:border-explorer-blue focus:ring-2 focus:ring-blue-100"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl bg-explorer-blue text-xs font-semibold text-white hover:bg-blue-700"
                        @click="handleCopy"
                    >
                        <Copy :size="14" /> Copy link
                    </button>
                    <Link
                        :href="collection.share_url"
                        class="flex h-9 flex-1 items-center justify-center gap-2 rounded-xl border border-explorer-line text-xs font-semibold text-explorer-blue hover:bg-blue-50"
                        >View public page <ArrowUpRight :size="14"
                    /></Link>
                </div>
                <p
                    v-if="message"
                    role="status"
                    class="text-[11px] text-explorer-green"
                >
                    {{ message }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
