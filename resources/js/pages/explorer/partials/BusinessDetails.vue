<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight } from 'lucide-vue-next';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
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

        <BusinessIdentity :place="place" />

        <p
            class="mt-3 text-[12px] leading-[1.7] whitespace-pre-line text-slate-500"
        >
            {{ place.description }}
        </p>

        <!-- Visit, share, and open the full profile -->
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
            <Link
                :href="show(place.slug).url"
                class="flex h-9 items-center justify-between rounded-xl border border-explorer-line px-3 text-[11px] font-semibold text-explorer-ink hover:bg-explorer-cloud"
            >
                View full profile
                <ArrowUpRight :size="15" class="text-slate-400" />
            </Link>
        </div>

        <div class="mt-4 border-t border-explorer-line">
            <BusinessContact :place="place" compact />
        </div>

        <div class="border-t border-explorer-line pt-4">
            <BusinessHours :place="place" compact />
        </div>
    </article>
</template>
