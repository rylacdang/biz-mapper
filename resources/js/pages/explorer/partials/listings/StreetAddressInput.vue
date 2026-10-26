<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import { computed, nextTick, ref, useId, watch } from 'vue';
import RequiredMark from '@/pages/explorer/partials/listings/RequiredMark.vue';
import type { AddressSuggestion } from '@/types';

const model = defineModel<string>({ required: true });
const props = defineProps<{
    suggestions: AddressSuggestion[];
    loading: boolean;
    error: string;
    searched: boolean;
}>();
const emit = defineEmits<{
    search: [];
    select: [suggestion: AddressSuggestion];
}>();
const id = useId();
const root = ref<HTMLElement>();
const input = ref<HTMLInputElement>();
const open = ref(false);
const active = ref(-1);
const expanded = computed(
    () => open.value && (props.loading || props.error !== '' || props.searched),
);

watch(
    () => props.suggestions,
    () => {
        active.value = -1;
    },
);

function handleFocus() {
    open.value = true;
    emit('search');
}

function handleFocusOut(event: FocusEvent) {
    if (!root.value?.contains(event.relatedTarget as Node | null)) {
        open.value = false;
    }
}

function handleSelect(suggestion: AddressSuggestion) {
    open.value = false;
    emit('select', suggestion);
    input.value?.focus({ preventScroll: true });
    open.value = false;
}

async function handleKeydown(event: KeyboardEvent) {
    if (!expanded.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        open.value = false;
    } else if (event.key === 'Enter') {
        event.preventDefault();

        if (active.value >= 0 && props.suggestions[active.value]) {
            handleSelect(props.suggestions[active.value]);
        }
    } else if (
        ['ArrowDown', 'ArrowUp'].includes(event.key) &&
        props.suggestions.length
    ) {
        event.preventDefault();
        const count = props.suggestions.length;
        active.value =
            event.key === 'ArrowDown'
                ? (active.value + 1) % count
                : active.value <= 0
                  ? count - 1
                  : active.value - 1;
        await nextTick();
        root.value
            ?.querySelector(`#${CSS.escape(`${id}-option-${active.value}`)}`)
            ?.scrollIntoView({ block: 'nearest' });
    }
}
</script>

<template>
    <div ref="root" class="relative mt-[17px]" @focusout="handleFocusOut">
        <label :for="id" class="mb-2 block text-[11px] font-bold">
            Street address <RequiredMark />
        </label>
        <input
            :id="id"
            ref="input"
            v-model="model"
            class="w-full min-w-0 rounded-[9px] border border-[#dfe7f1] bg-slate-50 p-3 text-xs font-normal text-explorer-ink"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="expanded"
            :aria-controls="`${id}-options`"
            :aria-activedescendant="
                expanded && active >= 0 ? `${id}-option-${active}` : undefined
            "
            :aria-describedby="`${id}-help`"
            autocomplete="off"
            required
            maxlength="500"
            placeholder="House / unit number, building, and street"
            @focus="handleFocus"
            @input="open = true"
            @keydown="handleKeydown"
        />

        <p :id="`${id}-help`" class="mt-2 text-[10px] leading-5 text-slate-400">
            Suggestions help find the area. You can type your full address if it
            is not listed.
        </p>

        <!-- Suggestions stay beside the address field, above the remaining form fields. -->
        <div
            v-if="expanded"
            class="absolute top-full right-0 left-0 z-10 mt-1 overflow-hidden rounded-xl border border-explorer-line bg-white shadow-lg"
        >
            <p v-if="loading" class="p-3 text-xs text-slate-500" role="status">
                Finding addresses…
            </p>
            <p
                v-else-if="error"
                class="p-3 text-xs text-amber-700"
                role="status"
            >
                {{ error }}
            </p>
            <template v-else>
                <ul
                    :id="`${id}-options`"
                    role="listbox"
                    aria-label="Matching street addresses"
                    class="max-h-60 overflow-y-auto"
                >
                    <li
                        v-for="(suggestion, index) in suggestions"
                        :key="suggestion.id"
                        role="presentation"
                    >
                        <button
                            :id="`${id}-option-${index}`"
                            type="button"
                            role="option"
                            :aria-selected="active === index"
                            tabindex="-1"
                            :class="[
                                'flex w-full items-start gap-2 px-3 py-3 text-left text-xs font-normal text-explorer-ink hover:bg-blue-50',
                                active === index && 'bg-blue-50',
                            ]"
                            @mousedown.prevent
                            @click="handleSelect(suggestion)"
                        >
                            <MapPin
                                :size="16"
                                class="mt-0.5 shrink-0 text-explorer-blue"
                            />
                            {{ suggestion.label }}
                        </button>
                    </li>
                </ul>
                <p
                    v-if="!suggestions.length"
                    class="p-3 text-xs text-slate-500"
                    role="status"
                >
                    No matching address. Keep your full address and position the
                    pin on the map.
                </p>
                <p
                    class="border-t border-explorer-line px-3 py-2 text-[10px] text-slate-400"
                >
                    Address data
                    <a
                        href="https://www.openstreetmap.org/copyright"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="underline"
                        >© OpenStreetMap</a
                    >
                </p>
            </template>
        </div>
    </div>
</template>
