<script setup lang="ts">
import {
    Facebook,
    Instagram,
    Link2,
    Globe,
    MapPin,
    Phone,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { SearchBusiness } from '@/types';
import { websiteUrl } from '@/utils/businesses';

const props = withDefaults(
    defineProps<{ place: SearchBusiness; compact?: boolean }>(),
    {
        compact: false,
    },
);

const socialLinks = computed(() =>
    [
        { key: 'facebook' as const, label: 'Facebook', icon: Facebook },
        { key: 'instagram' as const, label: 'Instagram', icon: Instagram },
        { key: 'tiktok' as const, label: 'TikTok', icon: Link2 },
    ]
        .map((platform) => ({
            ...platform,
            url: websiteUrl(props.place.social_links?.[platform.key]),
        }))
        .filter((platform) => platform.url),
);
</script>

<template>
    <!-- Address and verified-format external contact links -->
    <div
        :class="[
            'divide-y divide-explorer-line text-slate-600',
            compact ? 'text-[12px] leading-[1.6]' : 'text-sm leading-relaxed',
        ]"
    >
        <div :class="['flex items-start gap-3', compact ? 'py-3' : 'py-4']">
            <MapPin :size="18" class="mt-0.5 shrink-0 text-explorer-blue" />
            <p>{{ place.address }}</p>
        </div>

        <a
            v-if="place.phone && place.phone.replace(/[^+\d]/g, '')"
            :href="`tel:${place.phone.replace(/[^+\d]/g, '')}`"
            :class="[
                'flex items-center gap-3 hover:text-explorer-blue',
                compact ? 'py-3' : 'py-4',
            ]"
        >
            <Phone :size="18" class="shrink-0 text-explorer-blue" />
            <span>{{ place.phone }}</span>
        </a>

        <a
            v-if="websiteUrl(place.website)"
            :href="websiteUrl(place.website)"
            target="_blank"
            rel="noopener noreferrer"
            :class="[
                'flex items-center gap-3 hover:text-explorer-blue',
                compact ? 'py-3' : 'py-4',
            ]"
        >
            <Globe :size="18" class="shrink-0 text-explorer-blue" />
            <span>Visit website ↗</span>
        </a>
        <div v-if="socialLinks.length" class="flex flex-wrap gap-2 py-3">
            <a
                v-for="platform in socialLinks"
                :key="platform.key"
                :href="platform.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex h-9 items-center gap-2 rounded-lg border border-explorer-line bg-white px-3 text-[11px] font-medium text-explorer-blue hover:bg-blue-50"
            >
                <component :is="platform.icon" :size="14" aria-hidden="true" />
                {{ platform.label }}
            </a>
        </div>
    </div>
</template>
