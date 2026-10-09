<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessGallery from '@/components/businesses/BusinessGallery.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import WalkingDirections from '@/pages/explorer/partials/WalkingDirections.vue';
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
            class="back-link mb-5 flex items-center gap-2 text-[11px] text-slate-500 hover:text-explorer-blue"
            @click="emit('back')"
        >
            <ArrowLeft :size="16" />
            Back to places
        </button>

        <BusinessIdentity :place="place" />

        <p
            class="mt-3 text-[12px] leading-[1.85] whitespace-pre-line text-slate-500"
        >
            {{ place.description }}
        </p>

        <div v-if="place.gallery_photos?.length" class="mt-5">
            <BusinessGallery
                :photos="place.gallery_photos"
                :name="place.name"
                compact
            />
        </div>

        <!-- Visit, share, and open the full profile -->
        <div class="mt-5">
            <BusinessActions
                :place="place"
                show-profile
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

        <div class="mt-5 border-t border-explorer-line">
            <BusinessContact :place="place" compact />
        </div>

        <div class="mt-5 border-t border-explorer-line pt-5">
            <BusinessHours :place="place" compact />
        </div>
    </article>
</template>
