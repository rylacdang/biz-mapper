<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight } from 'lucide-vue-next';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import WalkingDirections from '@/pages/explorer/partials/WalkingDirections.vue';
import { show } from '@/routes/businesses';
import type { SearchBusiness } from '@/types';
import type { WalkingRoute } from '@/types/routing';

defineProps<{
    place: SearchBusiness;
    route: WalkingRoute | null;
    routing: boolean;
    routeError: string;
}>();

const emit = defineEmits<{ back: []; directions: []; clearRoute: [] }>();
</script>

<template>
    <!-- Shared business content also powers the standalone public profile -->
    <article class="p-5 max-md:pt-1">
        <button
            class="back-link mb-4 flex items-center gap-2 text-[11px] text-slate-500 hover:text-explorer-blue"
            @click="emit('back')"
        >
            <ArrowLeft :size="16" />
            Back to places
        </button>

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
