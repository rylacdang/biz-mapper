<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { show } from '@/routes/businesses';
import { show as summaryShow } from '@/routes/businesses/review-summary';
import type { ReviewSummary } from '@/types';
import { latestRequest } from '@/utils/requests';

const props = defineProps<{
    businessSlug: string;
    refreshKey?: object;
    showReviewsLink?: boolean;
}>();
const summary = ref<ReviewSummary | null>(null);
const fetching = ref(false);
const requests = latestRequest();
const reviewsUrl = computed(
    () => show.url(props.businessSlug) + '#reviews-title',
);
let pollTimer: ReturnType<typeof setTimeout> | undefined;
let deadlineTimer: ReturnType<typeof setTimeout> | undefined;
let deadline = 0;

const preparing = computed(
    () =>
        fetching.value ||
        summary.value?.status === 'pending' ||
        summary.value?.status === 'generating',
);

const updatedDate = computed(() => {
    if (!summary.value?.generated_at) {
        return '';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(summary.value.generated_at));
});

function stopPolling() {
    clearTimeout(pollTimer);
    clearTimeout(deadlineTimer);
    requests.cancel();
    fetching.value = false;
}

async function load() {
    const request = requests.start();
    fetching.value = true;

    try {
        const response = await fetch(summaryShow.url(props.businessSlug), {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Summary unavailable');
        }

        const result = (await response.json()) as { data: ReviewSummary };

        if (!request.isCurrent()) {
            return;
        }

        summary.value = result.data;

        if (
            ['pending', 'generating'].includes(result.data.status) &&
            Date.now() + 15_000 < deadline
        ) {
            pollTimer = setTimeout(() => void load(), 15_000);
        } else {
            clearTimeout(deadlineTimer);
        }
    } catch {
        if (request.isCurrent()) {
            clearTimeout(deadlineTimer);

            if (summary.value) {
                summary.value = {
                    ...summary.value,
                    status: 'unavailable',
                    text: null,
                };
            }
        }
    } finally {
        if (request.isCurrent()) {
            fetching.value = false;
        }
    }
}

watch(
    [() => props.businessSlug, () => props.refreshKey],
    ([slug], previous) => {
        stopPolling();

        // Keep known eligibility during refreshes so the loading state can stay visible.
        if (slug !== previous?.[0]) {
            summary.value = null;
        }

        deadline = Date.now() + 300_000;
        deadlineTimer = setTimeout(stopPolling, 300_000);
        void load();
    },
    { immediate: true },
);

onUnmounted(stopPolling);
</script>

<template>
    <section
        v-if="summary && summary.review_count >= 3"
        aria-label="AI review summary"
        :aria-busy="preparing"
        class="rounded-xl border border-blue-100 bg-blue-50/50 p-4"
    >
        <h2
            class="flex items-center gap-2 text-xs font-semibold text-explorer-ink"
        >
            <Sparkles
                :size="14"
                class="text-explorer-blue"
                :class="{ 'summary-sparkle': preparing }"
                aria-hidden="true"
            />
            What people say
        </h2>

        <div v-if="preparing" class="mt-3">
            <p role="status" class="summary-thinking text-[11px] font-medium">
                Preparing summary
            </p>
            <div aria-hidden="true" class="mt-3 space-y-2.5">
                <div class="summary-shimmer h-2.5 w-full rounded-full" />
                <div class="summary-shimmer h-2.5 w-[92%] rounded-full" />
                <div class="summary-shimmer h-2.5 w-[68%] rounded-full" />
            </div>
        </div>
        <p
            v-else-if="summary.status === 'ready' && summary.text"
            class="mt-2.5 text-xs leading-6 break-words whitespace-pre-line text-slate-600"
        >
            {{ summary.text }}
        </p>
        <p
            v-else
            role="status"
            class="mt-2 text-[11px] leading-5 text-slate-500"
        >
            {{
                summary.status === 'unavailable'
                    ? 'The AI summary is unavailable right now. You can still read the reviews.'
                    : 'A summary of recent reviews is being prepared. Check back shortly.'
            }}
        </p>

        <p v-if="!preparing" class="mt-3 text-[10px] leading-5 text-slate-400">
            AI-generated<span v-if="summary.status === 'ready' && updatedDate">
                · Updated {{ updatedDate }}</span
            >
            <span
                v-if="
                    summary.status === 'ready' &&
                    summary.review_count > summary.sampled_review_count
                "
                class="block"
                >Based on {{ summary.sampled_review_count }} recent reviews of
                {{ summary.review_count }} published reviews.</span
            >
        </p>
        <Link
            v-if="showReviewsLink"
            :href="reviewsUrl"
            class="mt-1 inline-flex h-9 items-center text-[11px] font-semibold text-explorer-blue hover:underline"
            >Read reviews</Link
        >
    </section>
</template>

<style scoped>
.summary-shimmer {
    background: linear-gradient(
        100deg,
        color-mix(
                in srgb,
                var(--color-explorer-blue) 9%,
                var(--color-explorer-cloud)
            )
            25%,
        color-mix(in srgb, var(--color-explorer-blue) 20%, white) 45%,
        color-mix(
                in srgb,
                var(--color-explorer-blue) 9%,
                var(--color-explorer-cloud)
            )
            65%
    );
    background-size: 240% 100%;
    animation: summary-shimmer 2.4s ease-in-out infinite;
}

.summary-thinking {
    width: fit-content;
    background: linear-gradient(
        100deg,
        var(--color-explorer-blue) 25%,
        color-mix(in srgb, var(--color-explorer-blue) 35%, white) 45%,
        var(--color-explorer-blue) 65%
    );
    background-size: 240% 100%;
    background-clip: text;
    color: transparent;
    animation: summary-shimmer 2.4s ease-in-out infinite;
}

.summary-sparkle {
    animation: summary-sparkle 2.4s ease-in-out infinite;
}

@keyframes summary-shimmer {
    from {
        background-position: 100% 0;
    }
    to {
        background-position: -100% 0;
    }
}

@keyframes summary-sparkle {
    50% {
        opacity: 0.45;
    }
}

@media (prefers-reduced-motion: reduce) {
    .summary-shimmer,
    .summary-thinking,
    .summary-sparkle {
        animation: none;
    }
}
</style>
