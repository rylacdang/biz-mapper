<script setup lang="ts">
import { Store } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { photoUrl } from '@/lib/explorer';
import type { SearchBusiness } from '@/types';

const props = withDefaults(
    defineProps<{ place: SearchBusiness; fullPage?: boolean }>(),
    { fullPage: false },
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
        :class="[
            'grid place-items-center overflow-hidden rounded-2xl bg-blue-50 text-blue-300',
            fullPage ? 'h-64 sm:h-72 lg:h-80' : 'h-52',
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

    <div :class="['flex items-start gap-4', fullPage ? 'mt-8' : 'mt-5']">
        <img
            v-if="photoUrl(place.logo) && !logoFailed"
            :src="photoUrl(place.logo)"
            :alt="`${place.name} logo`"
            :class="[
                'shrink-0 rounded-2xl border border-explorer-line bg-white object-contain p-1',
                fullPage ? 'size-18 sm:size-22' : 'size-12',
            ]"
            @error="logoFailed = true"
        />

        <div class="min-w-0">
            <span
                :class="
                    fullPage
                        ? 'text-xs font-semibold'
                        : 'text-[10px] font-[750]'
                "
                :style="{ color: place.category?.color }"
            >
                {{ place.category?.name || 'Local business' }}
            </span>

            <component
                :is="fullPage ? 'h1' : 'h2'"
                :class="[
                    'font-semibold text-explorer-ink',
                    fullPage
                        ? 'mt-2 font-explorer-heading text-3xl tracking-tight sm:text-4xl'
                        : 'mt-[7px] text-[23px] leading-[1.3] tracking-[-0.7px]',
                ]"
            >
                {{ place.name }}
            </component>
        </div>
    </div>
</template>
