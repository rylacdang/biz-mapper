<script setup lang="ts">
import { Check, ChevronDown, Search, Shapes } from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { Category } from '@/types';
import { categoryIcon } from '@/utils/categoryIcons';

const props = defineProps<{ categories: Category[] }>();
const category = defineModel<string>({ required: true });
const open = ref(false);
const query = ref('');
const searchInput = ref<HTMLInputElement>();

const selected = computed(() =>
    props.categories.find((item) => item.name === category.value),
);
const matching = computed(() =>
    props.categories.filter((item) =>
        item.name.toLowerCase().includes(query.value.trim().toLowerCase()),
    ),
);

watch(open, () => {
    query.value = '';
});

function handleSelect(value: string) {
    category.value = value;
    open.value = false;
}

function handleFocus(event: Event) {
    event.preventDefault();
    searchInput.value?.focus();
}
</script>

<template>
    <!-- A searchable picker keeps every category reachable without clipped chips. -->
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            type="button"
            aria-label="Choose business category"
            class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-explorer-line bg-white px-3 py-2 text-left hover:border-blue-300"
        >
            <span
                class="grid size-8 shrink-0 place-items-center rounded-lg bg-explorer-cloud"
            >
                <component
                    :is="selected ? categoryIcon(selected.icon) : Shapes"
                    :size="18"
                    :style="{ color: selected?.color || '#2563eb' }"
                    aria-hidden="true"
                />
            </span>
            <span
                class="min-w-0 flex-1 text-xs font-semibold text-explorer-ink"
            >
                {{ selected?.name || 'All places' }}
            </span>
            <ChevronDown :size="16" class="shrink-0 text-slate-400" />
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="8"
                class="z-50 flex max-h-[min(420px,var(--reka-popover-content-available-height))] w-[var(--reka-popover-trigger-width)] flex-col overflow-hidden rounded-2xl border border-explorer-line bg-white p-2 font-explorer-body text-explorer-ink scheme-light shadow-xl [&_:focus-visible]:outline-2 [&_:focus-visible]:outline-offset-2 [&_:focus-visible]:outline-explorer-blue [&_button]:cursor-pointer"
                aria-label="Business categories"
                @open-auto-focus="handleFocus"
            >
                <div
                    class="m-1 flex items-center gap-2 rounded-lg bg-explorer-cloud px-3"
                >
                    <Search :size="15" class="shrink-0 text-slate-400" />
                    <input
                        ref="searchInput"
                        v-model="query"
                        aria-label="Find a category"
                        placeholder="Find a category…"
                        class="h-10 min-w-0 flex-1 border-0 bg-transparent text-xs outline-none placeholder:text-slate-400"
                    />
                </div>
                <div
                    class="min-h-0 [scrollbar-width:thin] overflow-y-auto overscroll-contain p-1"
                >
                    <button
                        v-if="
                            !query.trim() ||
                            'all places'.includes(query.trim().toLowerCase())
                        "
                        type="button"
                        :aria-pressed="category === 'all'"
                        class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs hover:bg-explorer-cloud aria-pressed:bg-blue-50 aria-pressed:font-semibold"
                        @click="handleSelect('all')"
                    >
                        <Shapes
                            :size="17"
                            class="shrink-0 text-explorer-blue"
                        />
                        <span class="flex-1">All places</span>
                        <Check
                            v-if="category === 'all'"
                            :size="16"
                            class="text-explorer-blue"
                        />
                    </button>
                    <button
                        v-for="item in matching"
                        :key="item.id"
                        type="button"
                        :aria-pressed="category === item.name"
                        class="flex min-h-11 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs hover:bg-explorer-cloud aria-pressed:bg-blue-50 aria-pressed:font-semibold"
                        @click="handleSelect(item.name)"
                    >
                        <component
                            :is="categoryIcon(item.icon)"
                            :size="17"
                            :style="{ color: item.color }"
                            class="shrink-0"
                            aria-hidden="true"
                        />
                        <span class="flex-1">{{ item.name }}</span>
                        <Check
                            v-if="category === item.name"
                            :size="16"
                            class="shrink-0 text-explorer-blue"
                        />
                    </button>
                    <p
                        v-if="
                            !matching.length &&
                            query.trim() &&
                            !'all places'.includes(query.trim().toLowerCase())
                        "
                        class="px-3 py-5 text-center text-xs text-slate-500"
                    >
                        No categories match. Try another word.
                    </p>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
