<script setup lang="ts">
import { Store } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import type { SearchBusiness } from '@/types';
import { photoUrl } from '@/utils/businesses';

const props = withDefaults(
    defineProps<{
        place: SearchBusiness;
        fullPage?: boolean;
        showCover?: boolean;
    }>(),
    { fullPage: false, showCover: true },
);

const coverFailed = ref(false);
const logoFailed = ref(false);

watch(
    () => [props.place.cover_photo, props.place.logo],
    () => {
        coverFailed.value = false;
        logoFailed.value = false;
    },
);
</script>

<template>
    <!-- Real business images and a useful fallback when an upload is unavailable -->
    <div
        v-if="showCover"
        :class="[
            'grid place-items-center overflow-hidden rounded-2xl bg-blue-50 text-blue-300',
            fullPage ? 'h-64 sm:h-72 lg:h-80' : 'h-40',
        ]"
    >
        <img
            v-if="photoUrl(place.cover_photo) && !coverFailed"
            :src="photoUrl(place.cover_photo)"
            :alt="`${place.name} cover photo`"
            class="h-full w-full object-cover"
            @error="coverFailed = true"
        />

        <Store v-else :size="54" aria-hidden="true" />
    </div>

    <div
        :class="[
            'flex',
            fullPage ? 'items-center gap-4' : 'items-center gap-3',
            showCover ? (fullPage ? 'mt-8' : 'mt-4') : '',
        ]"
    >
        <img
            v-if="photoUrl(place.logo) && !logoFailed"
            :src="photoUrl(place.logo)"
            :alt="`${place.name} logo`"
            :class="[
                'shrink-0 border border-explorer-line bg-white object-contain p-1',
                fullPage
                    ? 'size-18 rounded-2xl sm:size-22'
                    : 'size-14 rounded-xl',
            ]"
            @error="logoFailed = true"
        />

        <div class="flex min-w-0 flex-1 flex-col">
            <span
                :class="
                    fullPage
                        ? 'text-xs font-semibold'
                        : 'order-2 mt-1 text-[10px] leading-relaxed font-semibold'
                "
                :style="{ color: place.category?.color }"
            >
                {{ place.category?.name || 'Local business' }}
            </span>

            <div
                class="flex min-w-0 items-center gap-2"
                :class="{ 'order-1': !fullPage }"
            >
                <component
                    :is="fullPage ? 'h1' : 'h2'"
                    :class="[
                        'min-w-0 flex-1 font-semibold break-words text-explorer-ink',
                        fullPage
                            ? 'mt-2 font-explorer-heading text-3xl tracking-tight sm:text-4xl'
                            : 'text-[20px] leading-snug tracking-tight',
                    ]"
                >
                    {{ place.name }}
                </component>

                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
