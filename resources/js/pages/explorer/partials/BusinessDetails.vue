<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import type { SearchBusiness } from '@/types';

defineProps<{ place: SearchBusiness }>();

const emit = defineEmits<{ back: [] }>();
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

        <!-- Visit, share, and open the full profile -->
        <div class="mt-5">
            <BusinessActions :place="place" show-profile />
        </div>

        <div class="mt-5 border-t border-explorer-line">
            <BusinessContact :place="place" compact />
        </div>

        <div class="mt-5 border-t border-explorer-line pt-5">
            <BusinessHours :place="place" compact />
        </div>
    </article>
</template>
