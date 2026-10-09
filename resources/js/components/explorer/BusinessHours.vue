<script setup lang="ts">
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatBusinessTime } from '@/lib/businessHours';
import type { SearchBusiness } from '@/types';

const props = withDefaults(
    defineProps<{ place: SearchBusiness; compact?: boolean }>(),
    { compact: false },
);

const orderedHours = computed(() =>
    [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ]
        .map((day) => ({ day, hours: props.place.hours?.[day] }))
        .filter((item) => item.hours),
);
</script>

<template>
    <!-- Hours describe the saved schedule without claiming live opening status -->
    <section v-if="orderedHours.length" aria-label="Business hours">
        <h3
            :class="[
                'mb-4 flex items-center gap-2 font-semibold text-explorer-ink',
                compact ? 'text-[12px]' : 'text-base',
            ]"
        >
            <Clock :size="compact ? 17 : 18" class="text-explorer-blue" />
            Business hours
        </h3>

        <dl :class="compact ? 'space-y-3 text-[11px]' : 'space-y-3 text-sm'">
            <div
                v-for="item in orderedHours"
                :key="item.day"
                class="flex justify-between gap-4"
            >
                <dt class="text-slate-500 capitalize">{{ item.day }}</dt>
                <dd class="text-right text-explorer-ink">
                    {{
                        item.hours!.closed
                            ? 'Closed'
                            : `${formatBusinessTime(item.hours!.open)} – ${formatBusinessTime(item.hours!.close)}`
                    }}
                </dd>
            </div>
        </dl>
    </section>
</template>
