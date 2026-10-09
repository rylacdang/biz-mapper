import { computed, onUnmounted, ref, watch } from 'vue';
import { show } from '@/routes/businesses';
import type { SearchBusiness } from '@/types';

export function useBusinessSharing(place: () => SearchBusiness) {
    const sharing = ref(false);
    const message = ref('');
    const showManualCopy = ref(false);
    let alive = true;

    const shareUrl = computed(() => {
        const path = show(place().slug).url;

        return typeof window === 'undefined'
            ? path
            : new URL(path, window.location.origin).href;
    });

    async function shareBusiness() {
        if (sharing.value) {
            return;
        }

        sharing.value = true;
        message.value = '';
        const url = shareUrl.value;

        try {
            if (navigator.share) {
                await navigator.share({
                    title: place().name,
                    url,
                });

                if (alive && url === shareUrl.value) {
                    message.value = 'Share options opened.';
                }
            } else {
                await navigator.clipboard.writeText(url);

                if (alive && url === shareUrl.value) {
                    message.value = 'Business link copied.';
                }
            }
        } catch (error) {
            // Closing the device share sheet should not open another dialog.
            if (
                alive &&
                url === shareUrl.value &&
                !(error instanceof Error && error.name === 'AbortError')
            ) {
                showManualCopy.value = true;
            }
        } finally {
            if (alive) {
                sharing.value = false;
            }
        }
    }

    watch(shareUrl, () => {
        message.value = '';
        showManualCopy.value = false;
    });

    onUnmounted(() => {
        alive = false;
    });

    return { shareUrl, sharing, message, showManualCopy, shareBusiness };
}
