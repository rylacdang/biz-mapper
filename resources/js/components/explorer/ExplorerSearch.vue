<script setup lang="ts">
import { Search, SlidersHorizontal } from 'lucide-vue-next';
import { categoryIcon } from '@/lib/categoryIcons';
import type { Category } from '@/types';

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
        <div
            :class="[
                'mx-0 mt-[13px] mb-[15px] flex max-h-19 [scrollbar-width:thin]',
                '[scrollbar-color:#dce5ef_transparent] flex-wrap gap-[6px] overflow-auto max-md:mx-0',
                'max-md:mt-[9px] max-md:mb-0 max-md:max-h-[none] max-md:[scrollbar-width:none]',
                'max-md:flex-nowrap max-md:pb-[2px]',
            ]"
            aria-label="Business categories"
        >
            <button
                :class="[
                    'flex items-center gap-[5px] rounded-[8px] border border-[#e5eaf1] bg-white px-[9px]',
                    'py-[6px] text-[10px] whitespace-nowrap text-[#68778f] data-[active=true]:border-[#cbdcff]',
                    'data-[active=true]:bg-[#edf3ff] data-[active=true]:font-[750]',
                    'data-[active=true]:text-explorer-blue max-md:px-[10px] max-md:py-[7px]',
                ]"
                type="button"
                :data-active="category === 'all'"
                @click="category = 'all'"
            >
                ✦ All places
            </button>

            <button
                :class="[
                    'group/step flex items-center gap-[5px] rounded-[8px] border border-[#e5eaf1] bg-white',
                    'px-[9px] py-[6px] text-[10px] whitespace-nowrap text-[#68778f]',
                    'data-[active=true]:border-[#cbdcff] data-[active=true]:bg-[#edf3ff]',
                    'data-[active=true]:font-[750] data-[active=true]:text-explorer-blue max-md:px-[10px]',
                    'max-md:py-[7px]',
                ]"
                v-for="item in categories"
                :key="item.id"
                type="button"
                :data-active="category === item.name"
                @click="category = item.name"
            >
                <component
                    :is="categoryIcon(item.icon)"
                    :size="14"
                    class="shrink-0"
                    :style="{ color: item.color }"
                    aria-hidden="true"
                />
                {{ item.name }}
            </button>
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
                {{ radius }} km
            </strong>
        </div>

        <input
            class="mt-3 block h-1 w-full cursor-pointer accent-explorer-blue"
            :id="`explorer-radius-${suffix}`"
            v-model.number="radius"
            type="range"
            min="1"
            max="50"
            aria-label="Search radius in kilometers"
        />

        <button
            v-if="!showKeyword"
            type="submit"
            :disabled="loading"
            class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-explorer-blue py-2.5 text-xs font-semibold text-white"
        >
            <Search :size="15" />
            Search this area
        </button>
    </form>
</template>
