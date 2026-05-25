<script setup lang="ts">
defineProps<{
    isOpen: boolean;
    title: string;
    subtitle?: string;
    tabs?: string[];
    currentTab?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'update:currentTab', tab: string): void;
}>();
</script>

<template>
    <div
        v-if="isOpen"
        class="animate-fade-in absolute inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-500/30 bg-gray-900/90 font-sans shadow-2xl backdrop-blur-xl"
        >
            <!-- Modal Header -->
            <div
                class="flex items-center justify-between border-b border-gray-500/10 px-6 py-4"
            >
                <div>
                    <h3
                        class="text-sm font-extrabold tracking-wider text-white uppercase"
                    >
                        {{ title }}
                    </h3>
                    <p v-if="subtitle" class="mt-0.5 text-[10px] text-gray-500">
                        {{ subtitle }}
                    </p>
                </div>
                <button
                    @click="emit('close')"
                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-900 hover:text-white"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Modal Tabs -->
            <div
                v-if="tabs && tabs.length > 0"
                class="flex border-b border-gray-500/10 bg-gray-900/35 px-4 text-xs font-semibold"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    @click="emit('update:currentTab', tab)"
                    class="border-b-2 px-4 py-3 capitalize transition"
                    :class="[
                        currentTab === tab
                            ? 'border-primary text-primary-light'
                            : 'border-transparent text-gray-500 hover:text-gray-300',
                    ]"
                >
                    {{ tab }}
                </button>
            </div>

            <!-- Modal Form Content Container -->
            <slot />
        </div>
    </div>
</template>
