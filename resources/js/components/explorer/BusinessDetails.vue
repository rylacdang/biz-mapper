<script setup lang="ts">
import { ArrowLeft, MapPin, Phone, Globe, Clock, Store } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatBusinessTime } from '@/lib/businessHours';
import { photoUrl, websiteUrl } from '@/lib/explorer';
import type { SearchBusiness } from '@/types';

const props = defineProps<{ place: SearchBusiness }>();

const orderedHours = computed(() =>
    [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ]
        .map((day) => ({ day, hours: props.place.hours?.[day] }))
        .filter((item) => item.hours),
);

const emit = defineEmits<{ back: [] }>();
</script>

<template>
    <!-- Selected business details -->
    <article class="p-5 max-md:pt-1">
        <button
            class="back-link flex items-center gap-2 border-0 bg-transparent px-0 pt-0 pb-[18px] text-[11px] text-[#657992]"
            @click="emit('back')"
        >
            <ArrowLeft :size="16" />
            Back to places
        </button>

        <!-- Business photo -->
        <div
            class="grid h-[210px] place-items-center overflow-hidden rounded-[14px] bg-[#edf3fa] text-[#b0c3dc]"
        >
            <img
                class="h-full w-full object-cover"
                v-if="photoUrl(place.cover_photo)"
                :src="photoUrl(place.cover_photo)"
                :alt="place.name"
            />

            <Store v-else :size="48" />
        </div>

        <span
            class="mt-5 block text-[10px] font-[750]"
            :style="{ color: place.category?.color }"
        >
            {{ place.category?.name || 'Local business' }}
        </span>

        <h2
            class="mx-0 mt-[7px] mb-3 text-[23px] leading-[1.3] tracking-[-0.7px]"
        >
            {{ place.name }}
        </h2>

        <p
            class="text-[12px] leading-[1.85] whitespace-pre-line text-[#7b8a9f]"
        >
            {{ place.description }}
        </p>

        <!-- Address and contact links -->
        <div
            class="flex items-start gap-3 border-t border-[#edf1f6] px-0 py-4 text-[12px] leading-[1.6] text-[#526b88] no-underline"
        >
            <MapPin class="shrink-0 text-explorer-blue" :size="19" />

            <p class="m-0">{{ place.address }}</p>
        </div>

        <a
            v-if="place.phone"
            class="flex items-start gap-3 border-t border-[#edf1f6] px-0 py-4 text-[12px] leading-[1.6] text-[#526b88] no-underline"
            :href="`tel:${place.phone.replace(/[^+\d]/g, '')}`"
        >
            <Phone class="shrink-0 text-explorer-blue" :size="18" />

            <span>{{ place.phone }}</span>
        </a>

        <a
            v-if="websiteUrl(place.website)"
            class="flex items-start gap-3 border-t border-[#edf1f6] px-0 py-4 text-[12px] leading-[1.6] text-[#526b88] no-underline"
            :href="websiteUrl(place.website)"
            target="_blank"
            rel="noopener noreferrer"
        >
            <Globe class="shrink-0 text-explorer-blue" :size="18" />

            <span>Visit website ↗</span>
        </a>

        <!-- Available operating hours -->
        <section v-if="place.hours">
            <h3 class="mx-0 my-4 flex items-center gap-[10px] text-[12px]">
                <Clock :size="17" />
                Business hours
            </h3>

            <div
                class="flex justify-between px-0 py-[6px] text-[11px] text-[#7c8ba0] capitalize"
                v-for="item in orderedHours"
                :key="item.day"
            >
                <span>{{ item.day }}</span>

                <span>
                    {{
                        item.hours!.closed
                            ? 'Closed'
                            : `${formatBusinessTime(item.hours!.open)} – ${formatBusinessTime(item.hours!.close)}`
                    }}
                </span>
            </div>
        </section>
    </article>
</template>
