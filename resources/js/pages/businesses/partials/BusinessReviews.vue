<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    MessageSquare,
    Pencil,
    Star,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { login } from '@/routes';
import { index } from '@/routes/businesses/reviews';
import { store, update, destroy } from '@/routes/consumer/reviews';
import {
    update as saveReply,
    destroy as deleteReply,
} from '@/routes/owner/reviews/reply';
import type { BusinessFeedback, BusinessReview, SearchBusiness } from '@/types';
import { latestRequest } from '@/utils/requests';

const props = defineProps<{
    initial: BusinessFeedback;
    business: SearchBusiness;
}>();
const page = usePage();
const feedback = ref(props.initial);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const requests = latestRequest();
const reviewOpen = ref(false);
const deleteOpen = ref(false);
const replyEditor = ref<number | null>(null);
const removal = ref<{
    kind: 'review' | 'reply';
    review: BusinessReview;
} | null>(null);
const form = useForm({ rating: 0, body: '' });
const replyForm = useForm({ body: '' });
const deleteForm = useForm({});
const mutationError = ref('');
const heading = ref<HTMLElement>();
const hasUser = computed(() => Boolean(page.props.auth?.user));
const busy = computed(
    () => form.processing || replyForm.processing || deleteForm.processing,
);
let requestedPage = 1;
let trigger: HTMLElement | null = null;

watch(
    () => props.initial,
    (initial) => {
        requests.cancel();
        feedback.value = initial;
        loading.value = false;
        error.value = '';
    },
);

watch(
    () => props.business.id,
    () => {
        reviewOpen.value = false;
        deleteOpen.value = false;
        replyEditor.value = null;
        notice.value = '';
        form.reset();
        form.clearErrors();
        replyForm.reset();
        replyForm.clearErrors();
    },
);

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

async function handlePage(target: number) {
    const request = requests.start();
    requestedPage = target;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(
            index.url(props.business.slug, { query: { page: target } }),
            {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('Reviews unavailable');
        }

        const result = (await response.json()) as BusinessFeedback;

        if (request.isCurrent()) {
            feedback.value = result;
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load reviews. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

function handleOpenReview(event: MouseEvent) {
    trigger = event.currentTarget as HTMLElement;
    form.rating = feedback.value.own_review?.rating ?? 0;
    form.body = feedback.value.own_review?.body ?? '';
    form.clearErrors();
    mutationError.value = '';
    reviewOpen.value = true;
}

function handleFailure() {
    mutationError.value = 'Could not save your changes. Please try again.';

    return false;
}

function handleSaveReview() {
    mutationError.value = '';
    const own = feedback.value.own_review;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            reviewOpen.value = false;
            notice.value = own
                ? 'Your review was updated.'
                : 'Your review was published.';
        },
        onHttpException: handleFailure,
        onNetworkError: handleFailure,
    };

    if (own) {
        form.put(
            update.url({ business: props.business.id, review: own.id }),
            options,
        );
    } else {
        form.post(store.url(props.business.id), options);
    }
}

function handleOpenReply(review: BusinessReview, event: MouseEvent) {
    trigger = event.currentTarget as HTMLElement;
    replyEditor.value = review.id;
    replyForm.body = review.reply?.body ?? '';
    replyForm.clearErrors();
    mutationError.value = '';

    void nextTick(() => document.getElementById('reply-' + review.id)?.focus());
}

function handleCancelReply() {
    replyEditor.value = null;
    trigger?.focus({ preventScroll: true });
}

function handleSaveReply(review: BusinessReview) {
    mutationError.value = '';
    replyForm.put(
        saveReply.url({ business: props.business.id, review: review.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                replyEditor.value = null;
                notice.value = 'Your reply was saved.';
                void nextTick(() =>
                    heading.value?.focus({ preventScroll: true }),
                );
            },
            onHttpException: handleFailure,
            onNetworkError: handleFailure,
        },
    );
}

function handleRemove(
    kind: 'review' | 'reply',
    review: BusinessReview,
    event: MouseEvent,
) {
    trigger = event.currentTarget as HTMLElement;
    removal.value = { kind, review };
    mutationError.value = '';
    deleteOpen.value = true;
}

function handleConfirmRemove() {
    const target = removal.value;

    if (!target) {
        return;
    }

    const params = { business: props.business.id, review: target.review.id };
    deleteForm.delete(
        target.kind === 'review'
            ? destroy.url(params)
            : deleteReply.url(params),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteOpen.value = false;
                reviewOpen.value = false;
                notice.value =
                    target.kind === 'review'
                        ? 'Your review was removed.'
                        : 'Your reply was removed.';
            },
            onHttpException: handleFailure,
            onNetworkError: handleFailure,
        },
    );
}

function handleRestoreFocus(event: Event) {
    event.preventDefault();
    const target = trigger?.isConnected ? trigger : heading.value;
    target?.focus({ preventScroll: true });
}

onUnmounted(() => {
    requests.cancel();
    form.cancel();
    replyForm.cancel();
    deleteForm.cancel();
});
</script>

<template>
    <section
        aria-labelledby="reviews-title"
        :aria-busy="loading"
        class="mt-10 border-t border-explorer-line pt-8"
    >
        <!-- Real ratings and a single entry point for the consumer's review. -->
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2
                    id="reviews-title"
                    ref="heading"
                    tabindex="-1"
                    class="font-explorer-heading text-base font-semibold"
                >
                    Neighborhood reviews
                </h2>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Experiences shared by people in the neighborhood.
                </p>
                <div
                    v-if="feedback.summary.count"
                    class="mt-3 flex items-center gap-2 text-xs"
                >
                    <Star
                        :size="16"
                        class="fill-amber-400 text-amber-400"
                        aria-hidden="true"
                    />
                    <strong class="text-base">{{
                        feedback.summary.average?.toFixed(1)
                    }}</strong>
                    <span class="text-slate-400"
                        >/ 5 · {{ feedback.summary.count }}
                        {{
                            feedback.summary.count === 1 ? 'review' : 'reviews'
                        }}</span
                    >
                </div>
            </div>
            <button
                v-if="feedback.can_review"
                type="button"
                :disabled="busy"
                class="inline-flex h-9 items-center gap-2 rounded-xl bg-explorer-blue px-3 text-[11px] font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                @click="handleOpenReview"
            >
                <Pencil :size="14" />
                {{
                    feedback.own_review ? 'Edit your review' : 'Write a review'
                }}
            </button>
            <Link
                v-else-if="!hasUser"
                :href="login.url()"
                class="inline-flex h-9 items-center rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold text-explorer-blue hover:bg-blue-50"
                >Log in to review</Link
            >
        </header>

        <p
            v-if="feedback.own_review?.status === 'hidden'"
            class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800"
        >
            Your review is not publicly visible. Editing it does not change its
            visibility. You can still edit or remove it.
        </p>
        <p v-if="notice" role="status" class="mt-4 text-xs text-explorer-green">
            {{ notice }}
        </p>
        <p
            v-if="error"
            role="alert"
            class="mt-4 rounded-xl bg-red-50 p-3 text-xs text-red-600"
        >
            {{ error }}
            <button
                type="button"
                class="ml-2 h-9 underline"
                @click="handlePage(requestedPage)"
            >
                Retry
            </button>
        </p>
        <p v-if="loading" role="status" class="mt-4 text-xs text-slate-500">
            Loading reviews...
        </p>

        <div
            v-if="feedback.reviews.data.length"
            class="mt-5 grid items-start gap-4 lg:grid-cols-2"
        >
            <article
                v-for="review in feedback.reviews.data"
                :key="review.id"
                :id="'review-' + review.id"
                class="min-w-0 rounded-2xl border border-explorer-line bg-white p-5"
            >
                <header class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-xs font-semibold break-words">
                            {{ review.author }}
                        </h3>
                        <time
                            :datetime="review.created_at"
                            class="mt-1 block text-[10px] text-slate-400"
                            >{{ formatDate(review.created_at) }}</time
                        >
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700"
                        :aria-label="review.rating + ' out of 5 stars'"
                        ><Star
                            :size="12"
                            class="fill-current"
                            aria-hidden="true"
                        />
                        {{ review.rating }} / 5</span
                    >
                </header>
                <p
                    class="mt-4 text-xs leading-6 break-words whitespace-pre-line text-slate-600"
                >
                    {{ review.body }}
                </p>
                <p
                    v-if="review.updated_at !== review.created_at"
                    class="mt-2 text-[10px] text-slate-400"
                >
                    Edited {{ formatDate(review.updated_at) }}
                </p>

                <!-- Replies belong to this business's owner, not another account. -->
                <div
                    v-if="review.reply && replyEditor !== review.id"
                    class="mt-4 border-l-2 border-blue-200 bg-blue-50/50 py-3 pr-3 pl-4"
                >
                    <p class="text-[11px] font-semibold text-explorer-blue">
                        Response from the owner
                    </p>
                    <time
                        :datetime="review.reply.updated_at"
                        class="mt-1 block text-[10px] text-slate-400"
                        >{{ formatDate(review.reply.updated_at) }}</time
                    >
                    <p
                        class="mt-2 text-xs leading-6 break-words whitespace-pre-line text-slate-600"
                    >
                        {{ review.reply.body }}
                    </p>
                </div>
                <div
                    v-if="feedback.can_reply && replyEditor !== review.id"
                    class="mt-3 flex gap-2"
                >
                    <button
                        type="button"
                        :disabled="busy"
                        class="inline-flex h-9 items-center gap-1.5 text-[11px] font-semibold text-explorer-blue"
                        @click="handleOpenReply(review, $event)"
                    >
                        <MessageSquare :size="13" />
                        {{ review.reply ? 'Edit reply' : 'Reply' }}
                    </button>
                    <button
                        v-if="review.reply"
                        type="button"
                        :disabled="busy"
                        class="inline-flex h-9 items-center gap-1.5 px-2 text-[11px] text-slate-500 hover:text-red-600"
                        @click="handleRemove('reply', review, $event)"
                    >
                        <Trash2 :size="13" /> Remove
                    </button>
                </div>
                <form
                    v-if="feedback.can_reply && replyEditor === review.id"
                    class="mt-4 space-y-3 border-t border-explorer-line pt-4"
                    @submit.prevent="handleSaveReply(review)"
                >
                    <label
                        :for="'reply-' + review.id"
                        class="block text-[11px] font-semibold"
                        >Your reply <span class="text-red-500">*</span></label
                    >
                    <textarea
                        :id="'reply-' + review.id"
                        v-model="replyForm.body"
                        required
                        minlength="2"
                        maxlength="2000"
                        rows="4"
                        class="w-full resize-y rounded-xl border border-explorer-line bg-explorer-cloud p-3 text-xs leading-6 outline-none focus:border-explorer-blue"
                        :aria-invalid="Boolean(replyForm.errors.body)"
                    />
                    <p
                        v-if="replyForm.errors.body || mutationError"
                        role="alert"
                        class="text-[11px] text-red-600"
                    >
                        {{ replyForm.errors.body || mutationError }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            :disabled="busy"
                            class="h-9 rounded-xl bg-explorer-blue px-3 text-[11px] font-semibold text-white disabled:opacity-50"
                        >
                            {{
                                replyForm.processing
                                    ? 'Saving...'
                                    : 'Save reply'
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="busy"
                            class="h-9 rounded-xl px-3 text-[11px] text-slate-500"
                            @click="handleCancelReply"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </article>
        </div>
        <div
            v-else-if="!loading && !error"
            class="mt-5 rounded-2xl border border-dashed border-explorer-line px-5 py-10 text-center"
        >
            <MessageSquare :size="25" class="mx-auto text-explorer-blue" />
            <h3 class="mt-3 text-[13px] font-semibold">No reviews yet</h3>
            <p class="mt-2 text-xs leading-6 text-slate-500">
                Visited this place? Share what your experience was like.
            </p>
        </div>
        <nav
            v-if="feedback.reviews.meta.last_page > 1"
            aria-label="Review pages"
            class="mt-5 flex items-center justify-between gap-3"
        >
            <button
                type="button"
                :disabled="
                    loading || busy || feedback.reviews.meta.current_page <= 1
                "
                class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold disabled:opacity-40"
                @click="handlePage(feedback.reviews.meta.current_page - 1)"
            >
                <ChevronLeft :size="14" /> Previous
            </button>
            <span aria-live="polite" class="text-[11px] text-slate-400"
                >{{ feedback.reviews.meta.current_page }} /
                {{ feedback.reviews.meta.last_page }}</span
            >
            <button
                type="button"
                :disabled="
                    loading ||
                    busy ||
                    feedback.reviews.meta.current_page >=
                        feedback.reviews.meta.last_page
                "
                class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold disabled:opacity-40"
                @click="handlePage(feedback.reviews.meta.current_page + 1)"
            >
                Next <ChevronRight :size="14" />
            </button>
        </nav>
    </section>

    <Dialog v-model:open="reviewOpen">
        <DialogContent
            class="max-h-[85dvh] overflow-y-auto rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-md"
            @close-auto-focus="handleRestoreFocus"
        >
            <DialogTitle class="font-explorer-heading text-base">{{
                feedback.own_review
                    ? 'Edit your review'
                    : 'Review ' + business.name
            }}</DialogTitle>
            <DialogDescription class="text-xs leading-6 text-slate-500"
                >Share your own experience. Your name and review are
                public.</DialogDescription
            >
            <form class="space-y-5" @submit.prevent="handleSaveReview">
                <fieldset>
                    <legend class="text-xs font-semibold">
                        Your rating <span class="text-red-500">*</span>
                    </legend>
                    <div class="mt-3 flex gap-2">
                        <label
                            v-for="rating in 5"
                            :key="rating"
                            class="relative cursor-pointer"
                        >
                            <input
                                v-model="form.rating"
                                type="radio"
                                name="rating"
                                :value="rating"
                                class="peer sr-only"
                                :aria-label="
                                    rating + (rating === 1 ? ' star' : ' stars')
                                "
                            />
                            <span
                                class="grid size-10 place-items-center rounded-xl border border-explorer-line peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-explorer-blue"
                                :class="
                                    form.rating >= rating
                                        ? 'bg-blue-50 text-explorer-blue'
                                        : 'text-slate-300'
                                "
                                ><Star
                                    :size="21"
                                    :class="
                                        form.rating >= rating
                                            ? 'fill-current'
                                            : ''
                                    "
                                    aria-hidden="true"
                            /></span>
                        </label>
                    </div>
                    <p
                        v-if="form.errors.rating"
                        role="alert"
                        class="mt-2 text-[11px] text-red-600"
                    >
                        {{ form.errors.rating }}
                    </p>
                </fieldset>
                <div>
                    <label for="review-body" class="text-xs font-semibold"
                        >Your experience
                        <span class="text-red-500">*</span></label
                    >
                    <textarea
                        id="review-body"
                        v-model="form.body"
                        required
                        minlength="10"
                        maxlength="2000"
                        rows="5"
                        placeholder="What stood out during your visit?"
                        class="mt-2 w-full resize-y rounded-xl border border-explorer-line bg-explorer-cloud p-3 text-xs leading-6 outline-none focus:border-explorer-blue"
                        :aria-invalid="Boolean(form.errors.body)"
                    />
                    <p class="mt-1 text-[10px] text-slate-400">
                        10–2,000 characters. Avoid sharing private information.
                    </p>
                    <p
                        v-if="form.errors.body"
                        role="alert"
                        class="mt-2 text-[11px] text-red-600"
                    >
                        {{ form.errors.body }}
                    </p>
                </div>
                <p
                    v-if="mutationError"
                    role="alert"
                    class="text-[11px] text-red-600"
                >
                    {{ mutationError }}
                </p>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button
                        v-if="feedback.own_review"
                        type="button"
                        :disabled="busy"
                        class="inline-flex h-9 items-center gap-1.5 text-[11px] text-red-600"
                        @click="
                            handleRemove('review', feedback.own_review, $event)
                        "
                    >
                        <Trash2 :size="13" /> Delete review
                    </button>
                    <button
                        type="submit"
                        :disabled="busy"
                        class="ml-auto h-9 rounded-xl bg-explorer-blue px-4 text-[11px] font-semibold text-white disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Saving...'
                                : feedback.own_review
                                  ? 'Save changes'
                                  : 'Publish review'
                        }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent
            class="rounded-2xl border-explorer-line bg-white font-explorer-body text-explorer-ink scheme-light sm:max-w-sm"
            @close-auto-focus="handleRestoreFocus"
        >
            <DialogTitle class="font-explorer-heading text-base"
                >Delete your {{ removal?.kind }}?</DialogTitle
            >
            <DialogDescription class="text-xs leading-6 text-slate-500">{{
                removal?.kind === 'review'
                    ? 'This removes your rating, written review, and any owner reply. You can write a new review later.'
                    : 'This removes your reply. The consumer’s review stays visible.'
            }}</DialogDescription>
            <p
                v-if="mutationError"
                role="alert"
                class="text-[11px] text-red-600"
            >
                {{ mutationError }}
            </p>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    :disabled="busy"
                    class="h-9 rounded-xl border border-explorer-line px-3 text-xs"
                    @click="deleteOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="busy"
                    class="h-9 rounded-xl bg-red-600 px-3 text-xs font-semibold text-white disabled:opacity-50"
                    @click="handleConfirmRemove"
                >
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
