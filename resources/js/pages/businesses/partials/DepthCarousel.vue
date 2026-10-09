<script setup lang="ts">
import { gsap } from 'gsap';
import { ChevronLeft, ChevronRight, Expand, ImageOff } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';

const props = defineProps<{
    items: { image: string; alt: string }[];
    name: string;
}>();

const emit = defineEmits<{ open: [index: number, event: Event] }>();
const root = ref<HTMLElement>();
const cards: (HTMLElement | null)[] = [];
const active = ref(0);
const width = ref(600);
const reduced = ref(false);
const failed = ref(new Set<string>());
const mobile = computed(() => width.value < 480);
const scale = computed(() =>
    Math.min(
        1,
        Math.max(0.35, (width.value - 32) / (420 + (mobile.value ? 28 : 60))),
    ),
);
const stageHeight = computed(() => Math.max(235, 280 * scale.value + 90));

let position = 0;
let tween: gsap.core.Tween | null = null;
let observer: ResizeObserver | null = null;
let motion: MediaQueryList | null = null;
let drag: { x: number; y: number; start: number; moved: boolean } | null = null;
let suppressClick = false;

function setCard(
    element: Element | ComponentPublicInstance | null,
    index: number,
) {
    cards[index] = element as HTMLElement | null;
}

// The supplied Vue Bits depth rail, scaled for landscape business photos.
function layout() {
    const count = props.items.length;

    cards.forEach((card, index) => {
        if (!card || !count) {
            return;
        }

        let distance = (((index - position) % count) + count) % count;

        if (distance > count / 2) {
            distance -= count;
        }

        const back = Math.max(0, distance);
        const opacity = reduced.value
            ? Number(index === active.value)
            : distance < 0
              ? Math.max(0, 1 + distance)
              : 1;
        const spread = mobile.value ? 40 : 90;
        const depth = mobile.value ? 140 : 200;
        const tilt = mobile.value ? 10 : 18;

        card.style.transform = reduced.value
            ? `translate(-50%, -50%) scale(${scale.value})`
            : `translate(-50%, -50%) scale(${scale.value}) translateX(${spread * distance}px) translateZ(${-depth * distance}px) rotateY(${tilt * Math.min(1, back)}deg)`;
        card.style.opacity = String(opacity);
        card.style.filter = reduced.value
            ? 'none'
            : `brightness(${Math.max(0.55, 1 - back * 0.12)}) blur(${Math.min(2, back * 0.6)}px)`;
        card.style.zIndex = String(Math.round(30 - distance * 4));
        card.style.pointerEvents = opacity > 0.05 ? 'auto' : 'none';
    });
}

function handleSelect(index: number, animate = true) {
    const count = props.items.length;

    if (!count) {
        return;
    }

    const target = ((index % count) + count) % count;
    let delta = (((target - position) % count) + count) % count;

    if (delta > count / 2) {
        delta -= count;
    }

    tween?.kill();
    const restoreCardFocus = cards.includes(
        document.activeElement as HTMLElement,
    );
    active.value = target;
    const proxy = { value: position };
    tween = gsap.to(proxy, {
        value: position + delta,
        duration: animate && !reduced.value ? 0.55 : 0,
        ease: 'power3.out',
        onUpdate: () => {
            position = proxy.value;
            layout();
        },
        onComplete: () => {
            position = target;
            layout();

            if (restoreCardFocus) {
                cards[target]?.focus({ preventScroll: true });
            }
        },
    });
}

function handleCard(index: number, event: Event) {
    if (suppressClick) {
        suppressClick = false;

        return;
    }

    if (index === active.value) {
        emit('open', index, event);
    } else {
        handleSelect(index);
    }
}

function handlePointerDown(event: PointerEvent) {
    if (
        props.items.length < 2 ||
        (event.pointerType === 'mouse' && event.button !== 0)
    ) {
        return;
    }

    suppressClick = false;
    drag = {
        x: event.clientX,
        y: event.clientY,
        start: position,
        moved: false,
    };
}

function handlePointerMove(event: PointerEvent) {
    if (!drag) {
        return;
    }

    const dx = event.clientX - drag.x;

    if (!drag.moved) {
        if (
            Math.abs(dx) < 8 ||
            Math.abs(dx) < Math.abs(event.clientY - drag.y)
        ) {
            return;
        }

        drag.moved = true;
        tween?.kill();
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    }

    suppressClick = true;
    position = drag.start - dx / Math.max(100, 420 * scale.value * 0.55);
    layout();
}

function handlePointerEnd(event: PointerEvent) {
    const moved = drag?.moved;
    drag = null;
    const element = event.currentTarget as HTMLElement;

    if (element.hasPointerCapture(event.pointerId)) {
        element.releasePointerCapture(event.pointerId);
    }

    if (moved) {
        handleSelect(Math.round(position));
    }
}

function handleMotion() {
    reduced.value = motion?.matches ?? false;
    handleSelect(active.value, false);
}

onMounted(() => {
    motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    motion.addEventListener('change', handleMotion);
    handleMotion();
    observer = new ResizeObserver(([entry]) => {
        width.value = entry.contentRect.width;
        layout();
    });

    if (root.value) {
        observer.observe(root.value);
    }
});

watch(
    () => props.items,
    async () => {
        tween?.kill();
        active.value = 0;
        position = 0;
        failed.value = new Set();
        await nextTick();
        layout();
    },
);

onUnmounted(() => {
    tween?.kill();
    observer?.disconnect();
    motion?.removeEventListener('change', handleMotion);
});
</script>

<template>
    <div
        ref="root"
        class="min-w-0"
        role="group"
        aria-roledescription="carousel"
        :aria-label="`${name} photos`"
        @keydown.left.prevent="handleSelect(active - 1)"
        @keydown.right.prevent="handleSelect(active + 1)"
    >
        <div
            class="relative isolate touch-pan-y select-none [perspective:1400px]"
            :style="{ height: `${stageHeight}px` }"
            @pointerdown="handlePointerDown"
            @pointermove="handlePointerMove"
            @pointerup="handlePointerEnd"
            @pointercancel="handlePointerEnd"
        >
            <div class="absolute inset-0 transform-3d">
                <button
                    v-for="(item, index) in items"
                    :key="item.image"
                    :ref="(element) => setCard(element, index)"
                    type="button"
                    :tabindex="active === index ? 0 : -1"
                    :aria-hidden="active !== index"
                    :aria-label="`Open photo ${index + 1} of ${name}`"
                    class="group/photo absolute top-1/2 left-1/2 h-[280px] w-[420px] overflow-hidden rounded-2xl border border-white bg-white shadow-[0_20px_50px_-15px_rgba(23,43,77,0.24)] will-change-transform"
                    @click="handleCard(index, $event)"
                >
                    <img
                        v-if="!failed.has(item.image)"
                        :src="item.image"
                        :alt="item.alt"
                        :loading="index ? 'lazy' : 'eager'"
                        draggable="false"
                        class="pointer-events-none h-full w-full object-contain"
                        @error="failed.add(item.image)"
                    />
                    <span
                        v-else
                        class="flex h-full flex-col items-center justify-center gap-3 bg-explorer-cloud text-xs text-slate-400"
                        ><ImageOff :size="28" /> Photo unavailable</span
                    >
                    <span
                        v-if="active === index"
                        class="pointer-events-none absolute right-3 bottom-3 grid size-9 place-items-center rounded-xl bg-white/90 text-explorer-ink opacity-0 shadow-sm transition-opacity group-hover/photo:opacity-100 group-focus-visible/photo:opacity-100 motion-reduce:transition-none"
                        ><Expand :size="16"
                    /></span>
                </button>
            </div>
        </div>

        <div class="relative flex items-center justify-between gap-3 px-2">
            <p class="text-[11px] text-slate-500">
                <span aria-live="polite" class="font-semibold text-explorer-ink"
                    >{{ active + 1 }} / {{ items.length }}</span
                ><span class="ml-2">Click a photo to enlarge</span>
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    :disabled="items.length < 2"
                    aria-label="Previous photo"
                    class="grid size-9 place-items-center rounded-full border border-explorer-line bg-white text-explorer-ink hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="handleSelect(active - 1)"
                >
                    <ChevronLeft :size="16" />
                </button>
                <button
                    type="button"
                    :disabled="items.length < 2"
                    aria-label="Next photo"
                    class="grid size-9 place-items-center rounded-full border border-explorer-line bg-white text-explorer-ink hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="handleSelect(active + 1)"
                >
                    <ChevronRight :size="16" />
                </button>
            </div>
        </div>
    </div>
</template>
