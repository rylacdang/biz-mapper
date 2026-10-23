<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Bookmark, BookmarkCheck, LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { login } from '@/routes';
import { destroy, store } from '@/routes/consumer/saved-places';
import type { SavedPlaceChange, SearchBusiness } from '@/types';

const props = withDefaults(
    defineProps<{
        business: SearchBusiness;
        compact?: boolean;
        subtle?: boolean;
    }>(),
    { compact: false, subtle: false },
);
const page = usePage();
const consumer = computed(() => page.props.auth?.user?.role === 'consumer');
const userId = computed(() => page.props.auth?.user?.id);
const saved = ref(Boolean(props.business.is_saved));
const error = ref('');
const form = useForm({});

watch(
    () => [props.business.id, props.business.is_saved, userId.value],
    () => {
        saved.value = consumer.value && Boolean(props.business.is_saved);
        error.value = '';
    },
);

function handleChange(event: Event) {
    const change = (event as CustomEvent<SavedPlaceChange>).detail;

    if (
        change.userId === userId.value &&
        change.businessId === props.business.id
    ) {
        saved.value = change.saved;
    }
}

function handleFailure() {
    error.value = 'Could not update this saved place. Please try again.';

    return false;
}

function handleToggle() {
    if (!consumer.value || form.processing) {
        return;
    }

    error.value = '';

    const next = !saved.value;
    const currentUser = userId.value;
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!currentUser || currentUser !== userId.value) {
                return;
            }

            saved.value = next;
            window.dispatchEvent(
                new CustomEvent<SavedPlaceChange>('saved-place-changed', {
                    detail: {
                        businessId: props.business.id,
                        userId: currentUser,
                        saved: next,
                    },
                }),
            );
        },
        onError: handleFailure,
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
    };

    if (next) {
        form.post(store.url(props.business.id), options);
    } else {
        form.delete(destroy.url(props.business.id), options);
    }
}

onMounted(() => window.addEventListener('saved-place-changed', handleChange));
onUnmounted(() => {
    form.cancel();
    window.removeEventListener('saved-place-changed', handleChange);
});
</script>

<template>
    <span
        v-if="page.props.auth?.user?.role !== 'owner'"
        class="business-save relative inline-flex shrink-0"
    >
        <button
            v-if="consumer"
            type="button"
            :disabled="form.processing"
            :aria-pressed="saved"
            :aria-label="`${saved ? 'Unsave' : 'Save'} ${business.name}`"
            :title="saved ? 'Remove from Saved places' : 'Save this place'"
            :class="[
                'inline-flex h-9 items-center justify-center gap-2 rounded-xl border text-xs font-semibold hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50',
                subtle
                    ? 'border-transparent bg-transparent'
                    : 'border-explorer-line bg-white',
                compact ? 'w-9' : 'px-3',
                saved ? 'text-explorer-blue' : 'text-slate-500',
            ]"
            @click="handleToggle"
        >
            <LoaderCircle
                v-if="form.processing"
                :size="15"
                class="animate-spin motion-reduce:animate-none"
            />
            <BookmarkCheck v-else-if="saved" :size="15" />
            <Bookmark v-else :size="15" />
            <span v-if="!compact">{{ saved ? 'Saved' : 'Save' }}</span>
        </button>
        <Link
            v-else
            :href="login.url()"
            :aria-label="`Log in to save ${business.name}`"
            title="Log in to save this place"
            :class="[
                'inline-flex h-9 items-center justify-center gap-2 rounded-xl border text-xs font-semibold text-slate-500 hover:bg-blue-50',
                subtle
                    ? 'border-transparent bg-transparent'
                    : 'border-explorer-line bg-white',
                compact ? 'w-9' : 'px-3',
            ]"
        >
            <Bookmark :size="15" /><span v-if="!compact">Save</span>
        </Link>
        <span
            v-if="error"
            role="alert"
            class="absolute top-full right-0 z-20 mt-2 w-52 rounded-xl border border-red-100 bg-white p-3 text-[11px] leading-5 text-red-600 shadow-lg"
            >{{ error }}</span
        >
    </span>
</template>
