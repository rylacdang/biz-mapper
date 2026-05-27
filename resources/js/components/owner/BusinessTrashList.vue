<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { Business } from '@/types';

defineProps<{
    trashBusinesses: Business[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const handleRestore = (id: number) => {
    router.post(
        `/owner/business/${id}/restore`,
        {},
        {
            onSuccess: () => {
                router.reload({
                    only: ['trashBusinesses', 'myBusinesses', 'allBusinesses'],
                });
            },
        },
    );
};
</script>

<template>
    <div class="flex max-h-[60vh] flex-col overflow-y-auto p-6 text-xs">
        <div
            v-if="!trashBusinesses || trashBusinesses.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center text-gray-500"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="mb-3 h-12 w-12 text-gray-600"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
            </svg>
            <p class="font-bold text-gray-400">Recycle Bin is Empty</p>
            <p class="mt-1 text-[10px]">No soft-deleted listings found.</p>
        </div>
        <div v-else class="space-y-3">
            <div
                v-for="biz in trashBusinesses"
                :key="biz.id"
                class="flex items-center gap-3 rounded-xl border border-gray-500/10 bg-gray-900/40 p-3"
            >
                <div
                    class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg bg-gray-800"
                >
                    <img
                        :src="
                            biz.cover_photo
                                ? '/storage/' + biz.cover_photo
                                : '/img/placeholder.jpg'
                        "
                        class="h-full w-full object-cover"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="truncate text-xs font-bold text-gray-200">
                            {{ biz.name }}
                        </h4>
                        <span
                            class="rounded bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-bold text-rose-400"
                        >
                            Deleted
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-[10px] text-gray-500">
                        {{ biz.address }}
                    </p>
                </div>
                <button
                    @click="handleRestore(biz.id)"
                    class="flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-2 font-bold text-emerald-400 transition-all hover:bg-emerald-500/20 hover:text-emerald-300 active:scale-95"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-3.5 w-3.5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17"
                        />
                    </svg>
                    Restore
                </button>
            </div>
        </div>
    </div>
</template>
