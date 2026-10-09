<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import CategoryPicker from '@/pages/explorer/partials/CategoryPicker.vue';
import type { Category } from '@/types';
import { formatDistance } from '@/utils/geography';

withDefaults(
    defineProps<{
        categories: Category[];
        loading: boolean;
        suffix?: string;
        showKeyword?: boolean;
    }>(),
    { suffix: 'desktop', showKeyword: true },
);

const keyword = defineModel<string>('keyword', { required: true });

const category = defineModel<string>('category', { required: true });

const radius = defineModel<number>('radius', { required: true });

const emit = defineEmits<{ search: []; reset: [] }>();

function handleRadius(values: number[] | undefined) {
    if (values?.[0] !== undefined) {
        radius.value = values[0];
    }
}
</script>

<template>
    <!-- Combined keyword, category, and radius filters -->
    <form
        class="border-b border-explorer-line px-5 pt-0 pb-[17px] max-md:border-0 max-md:p-[9px]"
        @submit.prevent="emit('search')"
    >
        <div
            v-if="showKeyword"
            class="flex items-center gap-[9px] rounded-[12px] border border-[#e2e9f2] bg-explorer-cloud [padding:5px_5px_5px_12px]"
        >
            <Search class="shrink-0 text-[#8c9bb0]" :size="19" />

            <input
                class="min-w-0 flex-1 border-none bg-transparent px-0 py-2 text-[11px] text-explorer-ink outline-none placeholder:text-[#8997ab]"
                v-model="keyword"
                aria-label="Search businesses"
                placeholder="Coffee, a haircut, something new…"
            />

            <button
                class="h-8 w-8 rounded-[9px] border-0 bg-explorer-blue text-[20px] text-white"
                type="submit"
                :disabled="loading"
                aria-label="Search nearby"
            >
                <span>↗</span>
            </button>
        </div>

        <!-- Category selection -->
        <div class="mt-3 mb-4 space-y-2 max-md:mt-0 max-md:mb-3">
            <span
                class="block text-[11px] font-semibold text-explorer-ink max-md:hidden"
                >Category</span
            >
            <CategoryPicker v-model="category" :categories="categories" />
        </div>

        <!-- Radius selection -->
        <div
            class="flex items-center justify-between text-[10px] text-[#8b98ac]"
        >
            <label
                class="flex items-center gap-[6px]"
                :for="`explorer-radius-${suffix}`"
            >
                <SlidersHorizontal :size="14" />
                Search radius
            </label>

            <strong
                class="rounded-[5px] bg-[#edf3ff] px-[6px] py-[3px] font-[750] text-explorer-blue"
            >
                {{ formatDistance(radius) }}
            </strong>
        </div>

        <SliderRoot
            class="group/radius relative mt-1 flex h-6 w-full cursor-pointer touch-none items-center select-none"
            :model-value="[radius]"
            :min="DEFAULT_SEARCH_RADIUS"
            :step="0.5"
            :max="50"
            @update:model-value="handleRadius"
        >
            <SliderTrack
                class="relative h-1.5 w-full overflow-hidden rounded-full bg-slate-100"
            >
                <SliderRange
                    class="absolute h-full rounded-full bg-explorer-blue"
                />
            </SliderTrack>
            <SliderThumb
                :id="`explorer-radius-${suffix}`"
                aria-label="Search radius in kilometers"
                :aria-valuetext="formatDistance(radius)"
                :aria-describedby="`explorer-radius-help-${suffix}`"
                class="block size-4 rounded-full border border-explorer-blue bg-white group-active/radius:shadow-[0_0_0_4px_#cbd5e1]"
            />
        </SliderRoot>

        <p
            :id="`explorer-radius-help-${suffix}`"
            class="mt-3 text-[11px] leading-relaxed text-slate-500 max-md:sr-only"
        >
            Apply your category and radius with Search this area.
        </p>

        <button
            v-if="!showKeyword"
            type="submit"
            :disabled="loading"
            class="mt-4 flex h-9 w-full items-center justify-center gap-2 rounded-xl bg-explorer-blue text-xs font-semibold text-white"
        >
            <Search :size="15" />
            Search this area
        </button>
    </form>
</template>
