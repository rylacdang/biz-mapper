import { router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import business from '@/routes/owner/businesses';
import type { Business } from '@/types';

export function useOwnerWorkspace(owner: Ref<boolean>) {
    // Owner workspace and listing drawer
    const workspace = ref(false);
    const showingTrash = ref(false);
    const busy = ref(false);
    const trashError = ref('');

    const drawerOpen = ref(false);
    const editing = ref<Business | null>(null);

    let previousFocus: HTMLElement | null = null;
    let focusFrame: number | undefined;

    function handleOpenDrawer(listing: Business | null = null) {
        previousFocus = document.activeElement as HTMLElement;
        editing.value = listing;
        drawerOpen.value = true;
    }

    function handleCloseDrawer() {
        drawerOpen.value = false;
        focusFrame = requestAnimationFrame(() => previousFocus?.focus());
    }

    function handleToggleTrash() {
        showingTrash.value = !showingTrash.value;

        if (showingTrash.value) {
            router.reload({ only: ['trashBusinesses'] });
        }
    }

    function handleRestoreListing(id: number) {
        busy.value = true;
        trashError.value = '';
        router.post(
            business.restore(id).url,
            {},
            {
                preserveScroll: true,
                onError: () => {
                    trashError.value =
                        'Couldn’t restore this listing. Please try again.';
                },
                onFinish: () => {
                    busy.value = false;
                },
            },
        );
    }

    function handleShowExplore() {
        workspace.value = false;
    }

    function handleShowListings() {
        workspace.value = true;
    }

    watch(owner, (isOwner) => {
        if (!isOwner) {
            workspace.value = false;
            showingTrash.value = false;
            drawerOpen.value = false;
            editing.value = null;
        }
    });

    onUnmounted(() => {
        if (focusFrame !== undefined) {
            cancelAnimationFrame(focusFrame);
        }
    });

    return {
        workspace,
        showingTrash,
        busy,
        trashError,
        drawerOpen,
        editing,
        handleOpenDrawer,
        handleCloseDrawer,
        handleToggleTrash,
        handleRestoreListing,
        handleShowExplore,
        handleShowListings,
    };
}
