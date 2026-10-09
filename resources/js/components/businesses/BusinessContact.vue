<script setup lang="ts">
import { Globe, MapPin, Phone } from 'lucide-vue-next';
import type { SearchBusiness } from '@/types';
import { websiteUrl } from '@/utils/businesses';

withDefaults(defineProps<{ place: SearchBusiness; compact?: boolean }>(), {
    compact: false,
});
</script>

<template>
    <!-- Address and verified-format external contact links -->
    <div
        :class="[
            'divide-y divide-explorer-line text-slate-600',
            compact ? 'text-[12px] leading-[1.6]' : 'text-sm leading-relaxed',
        ]"
    >
        <div class="flex items-start gap-3 py-4">
            <MapPin :size="18" class="mt-0.5 shrink-0 text-explorer-blue" />
            <p>{{ place.address }}</p>
        </div>

        <a
            v-if="place.phone && place.phone.replace(/[^+\d]/g, '')"
            :href="`tel:${place.phone.replace(/[^+\d]/g, '')}`"
            class="flex items-center gap-3 py-4 hover:text-explorer-blue"
        >
            <Phone :size="18" class="shrink-0 text-explorer-blue" />
            <span>{{ place.phone }}</span>
        </a>

        <a
            v-if="websiteUrl(place.website)"
            :href="websiteUrl(place.website)"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-3 py-4 hover:text-explorer-blue"
        >
            <Globe :size="18" class="shrink-0 text-explorer-blue" />
            <span>Visit website ↗</span>
        </a>
    </div>
</template>
