<script setup lang="ts">
import { ArrowUpRight, MapPin, Store } from 'lucide-vue-next';
import type { SearchBusiness } from '@/types';
import { photoUrl } from '@/utils/businesses';
import { formatDistance } from '@/utils/geography';

defineProps<{
    places: SearchBusiness[];
    loading: boolean;
    canExpand?: boolean;
}>();

const emit = defineEmits<{ select: [id: number]; expand: [] }>();
</script>

<template>
    <!-- Search results and empty state -->
    <div
        class="flex flex-col gap-[17px] px-5 pt-0 pb-5 max-md:gap-[18px]"
        :aria-busy="loading"
    >
        <!-- Keyboard-accessible business cards -->
        <button
            v-for="place in places"
            :key="place.id"
            class="group/place block w-full border-0 bg-white p-0 text-left"
            @click="emit('select', place.id)"
        >
            <div
                class="relative grid h-36 place-items-center overflow-hidden rounded-[13px] bg-[#eef3fa] text-[#abc0da] max-md:h-[155px] md:[@media(max-height:800px)]:h-[130px]"
            >
                <img
                    class="h-full w-full object-cover transition-transform duration-300 group-hover/place:scale-[1.035] motion-reduce:transition-none"
                    v-if="photoUrl(place.cover_photo)"
                    :src="photoUrl(place.cover_photo)"
                    :alt="place.name"
                    loading="lazy"
                    @error="($event.target as HTMLImageElement).hidden = true"
                />

                <Store v-else :size="32" />

                <span
                    class="absolute bottom-[10px] left-[10px] rounded-[6px] px-2 py-[5px] text-[9px] font-[750] text-white shadow-[0_2px_10px_#0002]"
                    :style="{ background: place.category?.color || '#2563eb' }"
                >
                    {{ place.category?.name || 'Local business' }}
                </span>
            </div>

            <div class="px-[2px] pt-[10px] pb-0">
                <div class="flex items-center justify-between gap-2">
                    <h3
                        class="m-0 text-[12px] font-[650] tracking-[-0.2px] max-md:text-[14px]"
                    >
                        {{ place.name }}
                    </h3>

                    <ArrowUpRight class="shrink-0 text-[#9fadc1]" :size="18" />
                </div>

                <p
                    class="mx-0 mt-[5px] mb-0 flex items-center gap-1 overflow-hidden text-[10px] text-ellipsis whitespace-nowrap text-[#93a0b3] max-md:text-[11px]"
                >
                    <MapPin class="shrink-0" :size="12" />
                    {{ place.address }}
                </p>

                <span
                    v-if="place.distance !== undefined"
                    class="mt-[6px] block text-[10px] text-explorer-green"
                >
                    {{ formatDistance(place.distance) }} from search center
                </span>
            </div>
        </button>

        <!-- Empty search results -->
        <div
            v-if="!places.length && !loading"
            class="px-5 py-[35px] text-center text-[#8b9bb1]"
        >
            <Store class="mx-auto mt-0 mb-4 text-[#a4b9d4]" :size="34" />

            <h3 class="text-[13px] text-[#526b88]">No places here yet</h3>

            <p class="text-[12px] leading-[1.7]">
                Try a wider radius or a different category.
            </p>

            <button
                v-if="canExpand"
                type="button"
                class="mt-4 rounded-xl bg-explorer-blue px-4 py-2.5 text-xs font-semibold text-white hover:bg-blue-700"
                @click="emit('expand')"
            >
                Expand to 1 km
            </button>
        </div>
    </div>
</template>
