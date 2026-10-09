<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { photoUrl } from '@/utils/businesses';

const props = withDefaults(
    defineProps<{ photos?: string[]; name: string; compact?: boolean }>(),
    { photos: () => [], compact: false },
);
const current = ref(0);
const open = ref(false);
let trigger: HTMLElement | null = null;
const selectedPhoto = computed(() => props.photos[current.value]);

watch(
    () => props.photos,
    () => {
        current.value = 0;
        open.value = false;
    },
);

function handleOpen(index: number, event: Event) {
    trigger = event.currentTarget as HTMLElement;
    current.value = index;
    open.value = true;
}

function handleRestoreFocus(event: Event) {
    event.preventDefault();

    if (trigger?.isConnected) {
        trigger.focus({ preventScroll: true });
    }
}

function handleMove(offset: number) {
    current.value =
        (current.value + offset + props.photos.length) % props.photos.length;
}
</script>

<template>
    <section
        v-if="photos.length"
        aria-label="Business photo gallery"
        class="space-y-3"
    >
        <div class="flex items-center justify-between gap-3">
            <h2
                :class="
                    compact
                        ? 'text-[12px] font-semibold'
                        : 'font-explorer-heading text-xl font-semibold'
                "
            >
                Photos
            </h2>
            <span class="text-[11px] text-slate-400"
                >{{ photos.length }}
                {{ photos.length === 1 ? 'photo' : 'photos' }}</span
            >
        </div>
        <div
            :class="
                compact
                    ? 'flex gap-2 overflow-x-auto pb-2'
                    : 'grid grid-cols-2 gap-3 sm:grid-cols-3'
            "
        >
            <button
                v-for="(photo, index) in photos"
                :key="photo"
                type="button"
                :aria-label="`View photo ${index + 1} of ${name}`"
                :class="[
                    'overflow-hidden rounded-xl bg-explorer-cloud focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-explorer-blue',
                    compact ? 'h-24 w-32 shrink-0' : 'aspect-[4/3]',
                ]"
                @click="handleOpen(index, $event)"
            >
                <img
                    :src="photoUrl(photo)"
                    :alt="`${name} gallery photo ${index + 1}`"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform hover:scale-105 motion-reduce:transition-none"
                />
            </button>
        </div>
    </section>

    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[90dvh] max-w-4xl gap-4 overflow-y-auto rounded-2xl bg-white font-explorer-body text-explorer-ink scheme-light"
            @close-auto-focus="handleRestoreFocus"
            @keydown.left.prevent="handleMove(-1)"
            @keydown.right.prevent="handleMove(1)"
        >
            <DialogTitle class="pr-8 text-sm">{{ name }} · Photos</DialogTitle>
            <DialogDescription class="sr-only"
                >Business photos. Use the previous and next buttons or the left
                and right arrow keys.</DialogDescription
            >
            <img
                v-if="selectedPhoto"
                :src="photoUrl(selectedPhoto)"
                :alt="`${name} gallery photo ${current + 1}`"
                class="max-h-[65dvh] w-full rounded-xl bg-explorer-cloud object-contain"
            />
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    :disabled="photos.length < 2"
                    class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line px-3 text-xs font-semibold hover:bg-explorer-cloud disabled:opacity-40"
                    @click="handleMove(-1)"
                >
                    <ChevronLeft :size="15" /> Previous
                </button>
                <span aria-live="polite" class="text-xs text-slate-500"
                    >{{ current + 1 }} / {{ photos.length }}</span
                >
                <button
                    type="button"
                    :disabled="photos.length < 2"
                    class="inline-flex h-9 items-center gap-1 rounded-xl border border-explorer-line px-3 text-xs font-semibold hover:bg-explorer-cloud disabled:opacity-40"
                    @click="handleMove(1)"
                >
                    Next <ChevronRight :size="15" />
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
