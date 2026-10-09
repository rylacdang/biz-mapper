import { nextTick, onUnmounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { SearchBusiness } from '@/types';

export function useExplorerPanel(workspace: Ref<boolean>) {
    // Explorer panel and mobile sheet
    const selected = ref<SearchBusiness | null>(null);
    const panelScroll = ref<HTMLElement>();
    const panelOpen = ref(false);
    const sheet = ref<'collapsed' | 'half' | 'expanded'>('half');
    const startY = ref<number | null>(null);

    let panelTrigger: HTMLElement | null = null;
    let focusFrame: number | undefined;
    let active = true;

    function handleOpenPanel(mode: 'explore' | 'listings' = 'explore') {
        panelTrigger = document.activeElement as HTMLElement;
        workspace.value = mode === 'listings';
        selected.value = null;
        sheet.value = 'half';
        panelOpen.value = true;

        nextTick(() => {
            if (!active || !panelOpen.value) {
                return;
            }

            panelScroll.value
                ?.closest('aside')
                ?.querySelector<HTMLButtonElement>(
                    '[aria-label="Close explorer panel"]',
                )
                ?.focus({ preventScroll: true });
        });
    }

    function handleClosePanel() {
        panelOpen.value = false;
        focusFrame = requestAnimationFrame(() =>
            panelTrigger?.focus({ preventScroll: true }),
        );
    }

    function handleSheetDragEnd(event: PointerEvent) {
        if (startY.value === null) {
            return;
        }

        const delta = event.clientY - startY.value;
        startY.value = null;

        if (Math.abs(delta) > 35) {
            sheet.value =
                delta < 0
                    ? sheet.value === 'collapsed'
                        ? 'half'
                        : 'expanded'
                    : sheet.value === 'expanded'
                      ? 'half'
                      : 'collapsed';
        }
    }

    function handleSheetDragStart(event: PointerEvent) {
        if ((event.target as HTMLElement).closest('button')) {
            return;
        }

        startY.value = event.clientY;
        // Capture only the drag handle; capturing button taps would swallow their clicks.
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    }

    function handleExpandSheet() {
        sheet.value = sheet.value === 'collapsed' ? 'half' : 'expanded';
    }

    function handleCollapseSheet() {
        sheet.value = sheet.value === 'expanded' ? 'half' : 'collapsed';
    }

    watch(selected, async (place) => {
        await nextTick();

        if (!active || selected.value !== place) {
            return;
        }

        panelScroll.value?.scrollTo({ top: 0 });

        if (place) {
            panelScroll.value
                ?.querySelector<HTMLButtonElement>('.back-link')
                ?.focus({ preventScroll: true });
        }
    });

    onUnmounted(() => {
        active = false;

        if (focusFrame !== undefined) {
            cancelAnimationFrame(focusFrame);
        }
    });

    return {
        selected,
        panelScroll,
        panelOpen,
        sheet,
        handleOpenPanel,
        handleClosePanel,
        handleSheetDragEnd,
        handleSheetDragStart,
        handleExpandSheet,
        handleCollapseSheet,
    };
}
