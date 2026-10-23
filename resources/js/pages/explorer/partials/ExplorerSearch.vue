<script setup lang="ts">
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { computed } from 'vue';
import { DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import CategoryPicker from '@/pages/explorer/partials/CategoryPicker.vue';
import type { Category, DiscoveryShortcut } from '@/types';
import { categoryIcon } from '@/utils/categoryIcons';
import { formatDistance } from '@/utils/geography';

const props = withDefaults(
    defineProps<{
        categories: Category[];
        shortcuts: DiscoveryShortcut[];
        loading: boolean;
        suffix?: string;
        showKeyword?: boolean;
    }>(),
    { suffix: 'desktop', showKeyword: true },
);

const keyword = defineModel<string>('keyword', { required: true });

const category = defineModel<string>('category', { required: true });

const radius = defineModel<number>('radius', { required: true });

const shortcut = defineModel<string>('shortcut', { required: true });

const selectedShortcut = computed(() =>
    props.shortcuts.find((item) => item.id === shortcut.value),
);

const emit = defineEmits<{ search: []; reset: []; clearShortcut: [] }>();

function handleShortcut(id: string) {
    const deselecting = shortcut.value === id;
    shortcut.value = deselecting ? '' : id;
    category.value = 'all';

    if (deselecting) {
        emit('clearShortcut');
    }
}

function handleCategory(value: string) {
    shortcut.value = '';
    category.value = value;
}

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

        <!-- Shortcuts only prepare filters; submitting applies them to the map. -->
        <fieldset class="mt-3 mb-4 min-w-0">
            <legend class="mb-2 text-[11px] font-semibold text-explorer-ink">
                What are you looking for?
            </legend>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="item in shortcuts"
                    :key="item.id"
                    type="button"
                    :aria-pressed="shortcut === item.id"
                    :title="item.categories.join(', ')"
                    class="flex min-h-16 flex-col items-start justify-start gap-1.5 rounded-xl border border-explorer-line bg-white px-2.5 py-2 text-left text-[10px] leading-4 font-medium text-slate-600 transition-colors hover:border-blue-300 hover:bg-blue-50 aria-pressed:border-explorer-blue aria-pressed:bg-blue-50 aria-pressed:text-explorer-blue motion-reduce:transition-none"
                    @click="handleShortcut(item.id)"
                >
                    <component
                        :is="categoryIcon(item.icon)"
                        :size="16"
                        class="text-explorer-blue"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </button>
            </div>
        </fieldset>

        <section
            v-if="selectedShortcut"
            aria-label="Included shortcut categories"
            class="mb-4 rounded-xl bg-blue-50 px-3 py-2.5"
        >
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-[11px] font-semibold text-explorer-blue">
                    Includes
                </h3>
                <button
                    type="button"
                    aria-label="Clear discovery shortcut"
                    class="grid size-7 place-items-center rounded-md text-explorer-blue hover:bg-blue-100"
                    @click="handleShortcut(selectedShortcut.id)"
                >
                    <X :size="14" />
                </button>
            </div>
            <p class="mt-1 text-[10px] leading-5 text-slate-500">
                {{ selectedShortcut.categories.join(' · ') }}
            </p>
        </section>

        <!-- Category selection -->
        <div class="mt-3 mb-4 space-y-2 max-md:mt-0 max-md:mb-3">
            <span
                class="block text-[11px] font-semibold text-explorer-ink max-md:hidden"
                >Category</span
            >
            <CategoryPicker
                :model-value="category"
                :categories="categories"
                @update:model-value="handleCategory"
            />
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
            Choose a shortcut or category, then apply with Search this area.
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
