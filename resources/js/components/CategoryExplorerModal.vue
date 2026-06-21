<script setup lang="ts">
import * as SolidIcons from '@heroicons/vue/24/solid';
import { Search } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import BaseModal from '@/components/BaseModal.vue';
import type { Category } from '@/types';

const props = defineProps<{
    isOpen: boolean;
    categories: Category[];
    selectedCategory: string | number;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'selectCategory', categoryName: string | number): void;
}>();

const searchQuery = ref('');

const filteredCategories = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.categories;
    return props.categories.filter((cat) =>
        cat.name.toLowerCase().includes(query),
    );
});

const getIconComponent = (iconName: string) => {
    return (SolidIcons as Record<string, any>)[iconName] || null;
};

const handleSelect = (name: string | number) => {
    emit('selectCategory', name);
    emit('close');
};
</script>

<template>
    <BaseModal
        :is-open="isOpen"
        title="Category Directory Explorer"
        subtitle="Browse all available directory categories and filter map listings"
        @close="emit('close')"
    >
        <div
            class="flex max-h-[70vh] flex-col gap-4 overflow-y-auto bg-gray-950/20 p-6 text-xs"
        >
            <!-- Search Field -->
            <div class="relative">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search categories (e.g. Cafe, Shop...)"
                    class="w-full rounded-xl border border-gray-500/30 bg-gray-950/40 py-3 pr-4 pl-10 text-xs text-gray-100 placeholder-gray-500 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                />
                <Search
                    class="absolute top-3.5 left-3.5 h-4 w-4 text-gray-500"
                />
            </div>

            <!-- Categories Grid -->
            <div
                v-if="filteredCategories.length > 0"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <!-- All Services button -->
                <button
                    @click="handleSelect('all')"
                    class="group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border p-5 text-center transition-all duration-300 hover:scale-[1.02]"
                    :class="[
                        selectedCategory === 'all'
                            ? 'border-primary bg-primary/10 text-white shadow-lg shadow-primary/5'
                            : 'border-gray-500/15 bg-gray-900/30 text-gray-400 hover:border-gray-500/30 hover:bg-gray-900/50 hover:text-white',
                    ]"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-500/10 text-gray-300 transition duration-300 group-hover:bg-primary/20 group-hover:text-primary-light"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    </div>
                    <span class="text-xs font-bold tracking-wide"
                        >All Services</span
                    >
                </button>

                <!-- Dynamic Categories -->
                <button
                    v-for="cat in filteredCategories"
                    :key="cat.id"
                    @click="handleSelect(cat.name)"
                    class="group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border p-5 text-center transition-all duration-300 hover:scale-[1.02]"
                    :style="[
                        selectedCategory === cat.name
                            ? {
                                  borderColor: cat.color,
                                  backgroundColor: cat.color + '15',
                                  color: '#ffffff',
                                  boxShadow: '0 8px 24px ' + cat.color + '1a',
                              }
                            : {
                                  borderColor: 'rgba(255, 255, 255, 0.05)',
                                  backgroundColor: 'rgba(24, 24, 27, 0.3)',
                                  color: '#9ca3af',
                              },
                    ]"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl transition duration-300"
                        :style="{
                            backgroundColor: cat.color + '15',
                            color: cat.color,
                        }"
                    >
                        <component
                            v-if="getIconComponent(cat.icon)"
                            :is="getIconComponent(cat.icon)"
                            class="h-5 w-5"
                        />
                        <span v-else class="text-xs font-black">?</span>
                    </div>
                    <span class="text-xs font-bold tracking-wide">{{
                        cat.name
                    }}</span>
                </button>
            </div>

            <!-- Empty Search State -->
            <div
                v-else
                class="rounded-2xl border border-dashed border-gray-500/20 bg-gray-900/10 p-8 text-center"
            >
                <p class="text-xs leading-relaxed text-gray-500">
                    No categories match your search "{{ searchQuery }}". Try
                    searching for something else.
                </p>
            </div>
        </div>
    </BaseModal>
</template>
