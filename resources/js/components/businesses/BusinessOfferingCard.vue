<script setup lang="ts">
import { Package, Wrench } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { BusinessOffering } from '@/types';
import { offeringPrice, photoUrl } from '@/utils/businesses';

const props = defineProps<{ item: BusinessOffering }>();
const failed = ref(false);
const price = computed(() => offeringPrice(props.item));

watch(
    () => props.item.photo,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <article
        class="flex h-full flex-col overflow-hidden rounded-2xl border border-explorer-line bg-white"
    >
        <img
            v-if="item.photo && !failed"
            :src="photoUrl(item.photo)"
            :alt="item.name"
            loading="lazy"
            class="aspect-[16/10] w-full object-cover"
            @error="failed = true"
        />
        <div class="flex flex-1 flex-col p-4">
            <span
                class="mb-3 inline-flex items-center gap-1.5 text-[10px] font-medium text-explorer-blue"
            >
                <component
                    :is="item.type === 'service' ? Wrench : Package"
                    :size="13"
                    aria-hidden="true"
                />
                {{ item.type === 'service' ? 'Service' : 'Product' }}
            </span>
            <h3 class="text-sm leading-snug font-semibold text-explorer-ink">
                {{ item.name }}
            </h3>
            <p
                v-if="item.description"
                class="mt-2 text-xs leading-6 whitespace-pre-line text-slate-500"
            >
                {{ item.description }}
            </p>
            <p
                v-if="price"
                class="mt-4 text-xs font-semibold text-explorer-blue"
            >
                {{ price }}
            </p>
            <div v-if="$slots.actions" class="mt-auto flex gap-2 pt-5">
                <slot name="actions" />
            </div>
        </div>
    </article>
</template>
