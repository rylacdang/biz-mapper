<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    MessageSquare,
    Star,
    Store,
    X,
} from 'lucide-vue-next';
import { DialogContent, DialogOverlay, DialogPortal } from 'reka-ui';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/consumer/reviews';
import type { ReviewHistoryPage } from '@/types';
import { photoUrl } from '@/utils/businesses';
import { latestRequest } from '@/utils/requests';

const emit = defineEmits<{ close: [] }>();

const history = ref<ReviewHistoryPage | null>(null);
const loading = ref(false);
const error = ref('');
const content = ref<HTMLElement>();
const failedLogos = ref(new Set<number>());
const requests = latestRequest();
let requestedPage = 1;

async function load(target = 1, restoreFocus = false) {
    const request = requests.start();
    requestedPage = target;
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(index.url({ query: { page: target } }), {
            signal: request.signal,
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok || response.redirected) {
            throw new Error('Review history unavailable');
        }

        const result: ReviewHistoryPage = await response.json();

        if (!request.isCurrent()) {
            return;
        }

        history.value = result;
        requestedPage = result.meta.current_page;

        if (restoreFocus) {
            void nextTick(() => {
                content.value?.scrollTo({ top: 0 });
                content.value?.focus({ preventScroll: true });
            });
        }
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load your reviews. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

function handleOpenChange(open: boolean) {
    if (!open) {
        emit('close');
    }
}

function dateLabel(value: string) {
    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

onMounted(() => void load());
onUnmounted(() => requests.cancel());
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Dialog :open="true" @update:open="handleOpenChange">
            <DialogPortal>
                <DialogOverlay
                    class="fixed inset-0 z-50 bg-explorer-ink/35 backdrop-blur-[3px]"
                />
                <DialogContent
                    :class="[
                        'fixed inset-y-0 right-0 left-auto z-50 flex h-dvh w-140 max-w-full translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden',
                        'rounded-[24px_0_0_24px] border-0 bg-white p-0 font-explorer-body text-explorer-ink scheme-light shadow-[-10px_0_60px_#172b4d20]',
                        'max-md:w-full max-md:rounded-none sm:max-w-full',
                        'duration-200 data-[state=closed]:animate-out data-[state=closed]:slide-out-to-right data-[state=open]:animate-in data-[state=open]:slide-in-from-right motion-reduce:animate-none',
                        '[&_button]:cursor-pointer [&_button:disabled]:cursor-not-allowed',
                    ]"
                >
                    <header
                        class="flex shrink-0 items-start justify-between gap-4 border-b border-explorer-line px-[30px] pt-[27px] pb-6 max-md:px-5 max-md:py-[22px]"
                    >
                        <div>
                            <DialogTitle
                                class="flex items-center gap-2 font-explorer-heading text-[22px] font-medium tracking-[-0.8px] max-md:text-[20px]"
                            >
                                My reviews
                                <span
                                    v-if="history"
                                    class="rounded-md bg-blue-50 px-2 py-0.5 font-explorer-body text-[10px] font-semibold tracking-normal text-explorer-blue"
                                    >{{ history.meta.total }}</span
                                >
                            </DialogTitle>
                            <DialogDescription
                                class="mt-2 text-[11px] leading-5 text-slate-500"
                            >
                                A private list of the reviews you’ve written.
                                Open one to edit or remove it on the business
                                profile.
                            </DialogDescription>
                        </div>
                        <DialogClose as-child>
                            <button
                                type="button"
                                aria-label="Close my reviews"
                                class="grid size-9 shrink-0 place-items-center rounded-xl bg-explorer-cloud text-slate-500 hover:bg-blue-50 hover:text-explorer-blue"
                            >
                                <X :size="18" />
                            </button>
                        </DialogClose>
                    </header>

                    <section
                        ref="content"
                        tabindex="-1"
                        class="min-h-0 flex-1 overflow-y-auto px-[30px] py-5 outline-none max-md:px-5"
                        aria-label="Your reviews"
                        :aria-busy="loading"
                    >
                        <div
                            v-if="error"
                            role="alert"
                            class="mb-4 rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-[11px] leading-5 text-red-600"
                        >
                            <p>{{ error }}</p>
                            <button
                                type="button"
                                class="mt-1 h-9 rounded-lg px-2 font-semibold hover:bg-red-100"
                                @click="load(requestedPage)"
                            >
                                Try again
                            </button>
                        </div>

                        <div
                            v-if="loading"
                            role="status"
                            aria-label="Loading your reviews"
                            class="space-y-3"
                        >
                            <div
                                v-for="row in 3"
                                :key="row"
                                class="space-y-3 rounded-xl border border-explorer-line p-4 motion-safe:animate-pulse"
                            >
                                <div class="flex gap-3">
                                    <div
                                        class="size-10 rounded-lg bg-blue-50"
                                    ></div>
                                    <div class="flex-1 space-y-2 pt-1">
                                        <div
                                            class="h-3 w-3/4 rounded bg-slate-100"
                                        ></div>
                                        <div
                                            class="h-2 w-1/2 rounded bg-slate-100"
                                        ></div>
                                    </div>
                                </div>
                                <div class="h-3 rounded bg-slate-100"></div>
                                <div
                                    class="h-3 w-4/5 rounded bg-slate-100"
                                ></div>
                            </div>
                        </div>

                        <div v-else-if="history?.data.length" class="space-y-3">
                            <article
                                v-for="review in history.data"
                                :key="review.id"
                                class="min-w-0 rounded-xl border border-explorer-line bg-white p-4"
                            >
                                <header class="flex items-center gap-3">
                                    <span
                                        class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg border border-explorer-line bg-explorer-cloud"
                                    >
                                        <img
                                            v-if="
                                                photoUrl(
                                                    review.business?.logo,
                                                ) && !failedLogos.has(review.id)
                                            "
                                            :src="
                                                photoUrl(review.business?.logo)
                                            "
                                            alt=""
                                            class="size-full object-cover"
                                            @error="failedLogos.add(review.id)"
                                        />
                                        <Store
                                            v-else
                                            :size="18"
                                            class="text-slate-400"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="text-xs leading-5 font-semibold break-words"
                                        >
                                            {{
                                                review.business?.name ??
                                                'Unavailable business'
                                            }}
                                        </h3>
                                        <time
                                            :datetime="review.created_at"
                                            class="mt-0.5 block text-[10px] text-slate-400"
                                            >{{
                                                dateLabel(review.created_at)
                                            }}</time
                                        >
                                    </div>
                                    <Tooltip v-if="review.url">
                                        <TooltipTrigger as-child>
                                            <Link
                                                :href="review.url"
                                                :aria-label="
                                                    'View your review of ' +
                                                    review.business?.name
                                                "
                                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-explorer-line bg-blue-50/60 text-explorer-blue transition-colors hover:border-explorer-blue hover:bg-explorer-blue hover:text-white motion-reduce:transition-none"
                                            >
                                                <ArrowUpRight
                                                    :size="17"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            side="bottom"
                                            align="end"
                                            :side-offset="8"
                                            class="rounded-lg border border-white/10 bg-explorer-ink px-3 py-2 font-explorer-body text-[11px] font-medium text-white shadow-lg shadow-explorer-ink/20 motion-reduce:animate-none [&_svg]:bg-explorer-ink [&_svg]:fill-explorer-ink"
                                        >
                                            View review
                                        </TooltipContent>
                                    </Tooltip>
                                </header>

                                <div
                                    class="mt-3 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="inline-flex gap-0.5"
                                        role="img"
                                        :aria-label="
                                            review.rating + ' out of 5 stars'
                                        "
                                    >
                                        <Star
                                            v-for="star in 5"
                                            :key="star"
                                            :size="12"
                                            :class="
                                                star <= review.rating
                                                    ? 'fill-amber-400 text-amber-400'
                                                    : 'text-slate-200'
                                            "
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span
                                        class="text-[10px] font-semibold text-slate-500"
                                        >{{ review.rating }} / 5</span
                                    >
                                    <span
                                        v-if="review.status === 'hidden'"
                                        class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] text-amber-700"
                                        >Not public</span
                                    >
                                </div>

                                <p
                                    class="mt-2 line-clamp-4 text-[11px] leading-5 break-words whitespace-pre-line text-slate-600"
                                >
                                    {{ review.body }}
                                </p>
                                <div
                                    v-if="review.reply"
                                    class="mt-3 border-l-2 border-blue-100 pl-3"
                                >
                                    <p
                                        class="text-[10px] font-semibold text-explorer-ink"
                                    >
                                        Owner replied
                                    </p>
                                    <p
                                        class="mt-1 line-clamp-3 text-[11px] leading-5 break-words whitespace-pre-line text-slate-500"
                                    >
                                        {{ review.reply.body }}
                                    </p>
                                </div>

                                <p
                                    v-if="!review.url"
                                    class="mt-3 text-[10px] leading-5 text-slate-400"
                                >
                                    This business is currently unavailable.
                                </p>
                            </article>
                        </div>

                        <div
                            v-else-if="history && !error"
                            class="rounded-xl border border-dashed border-explorer-line px-4 py-9 text-center"
                        >
                            <MessageSquare
                                :size="24"
                                class="mx-auto text-explorer-blue"
                                aria-hidden="true"
                            />
                            <h3 class="mt-3 text-xs font-semibold">
                                No reviews yet
                            </h3>
                            <p
                                class="mt-2 text-[11px] leading-5 text-slate-500"
                            >
                                Your experiences will appear here after you
                                review a business.
                            </p>
                        </div>
                    </section>

                    <nav
                        v-if="history && history.meta.last_page > 1"
                        aria-label="Your review pages"
                        class="flex shrink-0 items-center justify-between gap-2 border-t border-explorer-line px-[30px] py-3 max-md:px-5"
                    >
                        <button
                            type="button"
                            :disabled="
                                loading || history.meta.current_page <= 1
                            "
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-2.5 text-[11px] font-semibold hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="load(history.meta.current_page - 1, true)"
                        >
                            <ChevronLeft :size="13" /> Previous
                        </button>
                        <span
                            aria-live="polite"
                            class="text-[10px] text-slate-400"
                            >{{ history.meta.current_page }} /
                            {{ history.meta.last_page }}</span
                        >
                        <button
                            type="button"
                            :disabled="
                                loading ||
                                history.meta.current_page >=
                                    history.meta.last_page
                            "
                            class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line px-2.5 text-[11px] font-semibold hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="load(history.meta.current_page + 1, true)"
                        >
                            Next <ChevronRight :size="13" />
                        </button>
                    </nav>
                </DialogContent>
            </DialogPortal>
        </Dialog>
    </TooltipProvider>
</template>
