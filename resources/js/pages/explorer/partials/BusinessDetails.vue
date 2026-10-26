<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import BusinessReviewSummary from '@/components/businesses/BusinessReviewSummary.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import WalkingDirections from '@/pages/explorer/partials/WalkingDirections.vue';
import { show } from '@/routes/businesses';
import type { SearchBusiness, CollectionPlaceNavigation } from '@/types';
import type { WalkingRoute } from '@/types/routing';

defineProps<{
    place: SearchBusiness;
    route: WalkingRoute | null;
    routing: boolean;
    routeError: string;
    collection?: CollectionPlaceNavigation | null;
}>();

const emit = defineEmits<{
    back: [];
    directions: [];
    clearRoute: [];
    collectionStep: [direction: -1 | 1];
    collectionRetry: [];
}>();
</script>

<template>
    <!-- Shared business content also powers the standalone public profile -->
    <article class="p-5 max-md:pt-1">
        <button
            class="back-link mb-4 flex items-center gap-2 text-[11px] text-slate-500 hover:text-explorer-blue"
            @click="emit('back')"
        >
            <ArrowLeft :size="16" />
            {{ collection ? 'Back to collection' : 'Back to places' }}
        </button>

        <nav
            v-if="collection"
            aria-label="Collection places"
            tabindex="-1"
            class="mb-4 rounded-xl border border-explorer-line bg-explorer-cloud p-3 outline-none"
            :aria-busy="collection.loading"
        >
            <p
                class="truncate text-[11px] font-semibold"
                :title="collection.name"
            >
                {{ collection.name }}
            </p>
            <div class="mt-2 flex items-center justify-between gap-2">
                <button
                    type="button"
                    data-collection-step="-1"
                    aria-label="Previous place"
                    :disabled="!collection.hasPrevious || collection.loading"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line bg-white px-2 text-[11px] font-semibold text-explorer-blue hover:border-explorer-blue disabled:cursor-not-allowed disabled:opacity-40"
                    @click="emit('collectionStep', -1)"
                >
                    <ChevronLeft :size="13" /> Previous
                </button>
                <span
                    aria-live="polite"
                    aria-atomic="true"
                    class="text-center text-[10px] text-slate-500"
                >
                    {{
                        collection.loading
                            ? 'Loading…'
                            : collection.position +
                              ' of ' +
                              collection.total +
                              (collection.total === 1 ? ' place' : ' places')
                    }}
                </span>
                <button
                    type="button"
                    data-collection-step="1"
                    aria-label="Next place"
                    :disabled="!collection.hasNext || collection.loading"
                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-explorer-line bg-white px-2 text-[11px] font-semibold text-explorer-blue hover:border-explorer-blue disabled:cursor-not-allowed disabled:opacity-40"
                    @click="emit('collectionStep', 1)"
                >
                    Next <ChevronRight :size="13" />
                </button>
            </div>
            <p
                v-if="collection.error"
                role="alert"
                class="mt-2 text-[11px] leading-5 text-red-600"
            >
                {{ collection.error }}
                <button
                    type="button"
                    class="ml-1 h-9 font-semibold underline"
                    @click="emit('collectionRetry')"
                >
                    Retry
                </button>
            </p>
        </nav>

        <BusinessIdentity :place="place">
            <template #actions>
                <TooltipProvider :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Link
                                :href="show(place.slug).url"
                                aria-label="View full profile"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl border border-explorer-line bg-blue-50/60 text-explorer-blue transition-colors hover:border-explorer-blue hover:bg-explorer-blue hover:text-white motion-reduce:transition-none"
                            >
                                <ArrowUpRight :size="17" aria-hidden="true" />
                            </Link>
                        </TooltipTrigger>

                        <TooltipContent
                            side="bottom"
                            align="end"
                            :side-offset="8"
                            class="rounded-lg border border-white/10 bg-explorer-ink px-3 py-2 font-explorer-body text-[11px] font-medium text-white shadow-lg shadow-explorer-ink/20 motion-reduce:animate-none [&_svg]:bg-explorer-ink [&_svg]:fill-explorer-ink"
                        >
                            View full profile
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </template>
        </BusinessIdentity>

        <p
            class="mt-3 text-[12px] leading-[1.7] whitespace-pre-line text-slate-500"
        >
            {{ place.description }}
        </p>

        <BusinessReviewSummary
            :business-slug="place.slug"
            show-reviews-link
            class="mt-4"
        />

        <!-- Visit and share this business -->
        <div class="mt-4 space-y-3">
            <BusinessActions
                :place="place"
                in-explorer
                :routing="routing"
                @directions="emit('directions')"
            />
            <WalkingDirections
                :route="route"
                :loading="routing"
                :error="routeError"
                @clear="emit('clearRoute')"
                @retry="emit('directions')"
            />
        </div>

        <div class="mt-4 border-t border-explorer-line">
            <BusinessContact :place="place" compact />
        </div>

        <div class="border-t border-explorer-line pt-4">
            <BusinessHours :place="place" compact />
        </div>
    </article>
</template>
