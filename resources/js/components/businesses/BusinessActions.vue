<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Footprints, Share2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useBusinessSharing } from '@/composables/useBusinessSharing';
import { show } from '@/routes/businesses';
import type { SearchBusiness } from '@/types';

const props = withDefaults(
    defineProps<{ place: SearchBusiness; showProfile?: boolean }>(),
    { showProfile: false },
);

const { shareUrl, sharing, message, showManualCopy, shareBusiness } =
    useBusinessSharing(() => props.place);

const directionsUrl = computed(() => {
    const { lat, lng } = props.place;

    if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng) ||
        Math.abs(lat) > 90 ||
        Math.abs(lng) > 180
    ) {
        return undefined;
    }

    // Google Maps chooses the origin without including the user's GPS coordinates.
    const parameters = new URLSearchParams({
        api: '1',
        destination: `${lat},${lng}`,
        travelmode: 'walking',
    });

    return `https://www.google.com/maps/dir/?${parameters}`;
});

const shareButton = ref<HTMLButtonElement>();

function handleShare() {
    void shareBusiness();
}

function handleRestoreShareFocus(event: Event) {
    event.preventDefault();
    shareButton.value?.focus();
}
</script>

<template>
    <!-- Actions work in both the map panel and public profile -->
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <a
                v-if="directionsUrl"
                :href="directionsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-xl bg-explorer-blue px-4 text-xs font-semibold text-white hover:bg-blue-700"
                aria-label="Get walking directions in Google Maps"
            >
                <Footprints :size="15" />
                Get directions
            </a>

            <button
                ref="shareButton"
                type="button"
                :disabled="sharing"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-xl border border-explorer-line bg-white px-4 text-xs font-semibold text-explorer-ink hover:bg-explorer-cloud disabled:opacity-50"
                @click="handleShare"
            >
                <Share2 :size="15" />
                {{ sharing ? 'Sharing…' : 'Share' }}
            </button>
        </div>

        <p class="text-xs text-slate-500">Directions open in Google Maps.</p>

        <p v-if="message" role="status" class="text-xs text-explorer-green">
            {{ message }}
        </p>

        <Link
            v-if="showProfile"
            :href="show(place.slug).url"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-explorer-blue hover:underline"
        >
            View full profile
            <ArrowUpRight :size="15" />
        </Link>
    </div>

    <!-- Clipboard-denied browsers still provide a selectable business link -->
    <Dialog v-model:open="showManualCopy">
        <DialogContent
            class="rounded-2xl bg-white font-explorer-body text-explorer-ink scheme-light"
            @close-auto-focus="handleRestoreShareFocus"
        >
            <DialogTitle>Share {{ place.name }}</DialogTitle>
            <DialogDescription class="text-slate-500">
                Automatic copying is unavailable. Select and copy this link.
            </DialogDescription>

            <label class="space-y-2 text-sm font-medium">
                <span>Business link</span>
                <input
                    :value="shareUrl"
                    readonly
                    class="w-full rounded-xl border border-explorer-line bg-explorer-cloud px-3 py-3 text-sm text-explorer-ink"
                    @focus="($event.target as HTMLInputElement).select()"
                />
            </label>
        </DialogContent>
    </Dialog>
</template>
