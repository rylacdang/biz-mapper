<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    MessageSquare,
} from 'lucide-vue-next';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, onUnmounted, ref, watch } from 'vue';
import { index, read, readAll } from '@/routes/notifications';
import { latestRequest } from '@/utils/requests';

type InboxNotification = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    created_at: string;
    read_at: string | null;
};

type InboxResponse = {
    notifications: {
        data: InboxNotification[];
        meta: { current_page: number; last_page: number; total: number };
    };
    unread_count: number;
};

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id);
const open = ref(false);
const unread = ref(page.props.notificationUnreadCount ?? 0);
const items = ref<InboxNotification[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const listRequest = latestRequest();
const writeRequest = latestRequest();

watch(
    () => page.props.notificationUnreadCount,
    (count) => {
        unread.value = count ?? 0;
    },
);

watch(userId, () => {
    listRequest.cancel();
    writeRequest.cancel();
    open.value = false;
    items.value = [];
    error.value = '';
    loading.value = false;
    saving.value = false;
    unread.value = page.props.notificationUnreadCount ?? 0;
});

watch(open, (visible) => {
    if (visible) {
        void loadPage(1);
    } else {
        listRequest.cancel();
        loading.value = false;
    }
});

async function loadPage(number: number) {
    const request = listRequest.start();
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(index.url({ query: { page: number } }), {
            headers: { Accept: 'application/json' },
            signal: request.signal,
            cache: 'no-store',
        });

        if (!response.ok || response.redirected) {
            throw new Error('Inbox unavailable');
        }

        const data: InboxResponse = await response.json();

        if (!request.isCurrent()) {
            return;
        }

        items.value = data.notifications.data;
        currentPage.value = data.notifications.meta.current_page;
        lastPage.value = data.notifications.meta.last_page;
        total.value = data.notifications.meta.total;
        unread.value = data.unread_count;
    } catch {
        if (request.isCurrent()) {
            error.value = 'Could not load notifications. Please try again.';
        }
    } finally {
        if (request.isCurrent()) {
            loading.value = false;
        }
    }
}

// Use Laravel's same-origin CSRF cookie for these JSON-only updates.
async function markRead(item?: InboxNotification) {
    if (saving.value) {
        return false;
    }

    const request = writeRequest.start();
    saving.value = true;
    error.value = '';

    try {
        const token = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice(11);
        const response = await fetch(item ? read.url(item.id) : readAll.url(), {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: request.signal,
        });

        if (!response.ok || response.redirected) {
            throw new Error('Update unavailable');
        }

        const data: { unread_count: number; read_at: string } =
            await response.json();

        if (!request.isCurrent()) {
            return false;
        }

        unread.value = data.unread_count;
        items.value = items.value.map((entry) =>
            !item || entry.id === item.id
                ? { ...entry, read_at: entry.read_at ?? data.read_at }
                : entry,
        );

        return true;
    } catch {
        if (request.isCurrent()) {
            error.value =
                'Could not mark notifications read. Please try again.';
        }

        return false;
    } finally {
        if (request.isCurrent()) {
            saving.value = false;
        }
    }
}

async function openNotification(item: InboxNotification) {
    if (saving.value) {
        return;
    }

    const recipient = userId.value;

    if (!item.read_at) {
        await markRead(item);
    }

    if (recipient !== userId.value || !item.url) {
        return;
    }

    open.value = false;
    router.visit(item.url);
}

function dateLabel(value: string) {
    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

onUnmounted(() => {
    listRequest.cancel();
    writeRequest.cancel();
});
</script>

<template>
    <PopoverRoot v-if="userId" v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :aria-label="
                    unread
                        ? 'Notifications, ' + unread + ' unread'
                        : 'Notifications'
                "
                class="relative inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-explorer-line bg-white text-explorer-ink hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-explorer-blue"
            >
                <Bell :size="17" />
                <span
                    v-if="unread"
                    class="absolute -top-1.5 -right-1.5 flex min-w-4 items-center justify-center rounded-full bg-explorer-blue px-1 text-[9px] leading-4 font-semibold text-white"
                    >{{ unread > 99 ? '99+' : unread }}</span
                >
            </button>
        </PopoverTrigger>
        <PopoverPortal>
            <PopoverContent
                align="end"
                :side-offset="12"
                :collision-padding="12"
                aria-label="Notifications"
                class="z-50 w-[min(360px,calc(100vw-24px))] overflow-hidden rounded-2xl border border-explorer-line bg-white font-explorer-body text-explorer-ink shadow-xl shadow-explorer-ink/10 outline-none [&_button]:cursor-pointer [&_button:disabled]:cursor-not-allowed"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-explorer-line px-4 py-3"
                >
                    <div>
                        <h2 class="text-sm font-semibold">Notifications</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            {{
                                unread
                                    ? unread + ' unread'
                                    : 'You’re all caught up'
                            }}
                        </p>
                    </div>
                    <button
                        v-if="unread"
                        type="button"
                        :disabled="saving || loading"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2 text-[11px] font-semibold text-explorer-blue hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="markRead()"
                    >
                        <CheckCheck :size="15" /> Mark all read
                    </button>
                </div>

                <div
                    v-if="error"
                    role="alert"
                    class="flex items-center justify-between gap-2 border-b border-explorer-line px-4 py-3 text-[11px] text-red-600"
                >
                    {{ error }}
                    <button
                        type="button"
                        :disabled="loading || saving"
                        class="h-9 shrink-0 rounded-lg px-2 font-semibold hover:bg-red-50"
                        @click="loadPage(currentPage)"
                    >
                        Retry
                    </button>
                </div>
                <div
                    v-if="loading"
                    role="status"
                    aria-label="Loading notifications"
                    class="space-y-4 p-4"
                >
                    <div
                        v-for="row in 3"
                        :key="row"
                        class="space-y-2 motion-safe:animate-pulse"
                    >
                        <div class="h-3 w-24 rounded bg-blue-100"></div>
                        <div class="h-3 w-full rounded bg-slate-100"></div>
                        <div class="h-2 w-32 rounded bg-slate-100"></div>
                    </div>
                </div>
                <div
                    v-else-if="!items.length && !error"
                    class="flex flex-col items-center px-6 py-10 text-center"
                >
                    <span
                        class="mb-3 grid size-10 place-items-center rounded-full bg-blue-50 text-explorer-blue"
                        ><Bell :size="19"
                    /></span>
                    <p class="text-xs font-semibold">Nothing here yet</p>
                    <p
                        class="mt-1 max-w-60 text-[11px] leading-5 text-slate-500"
                    >
                        New reviews and owner replies will appear here.
                    </p>
                </div>
                <ul
                    v-else-if="!loading"
                    class="max-h-[min(440px,55dvh)] divide-y divide-explorer-line overflow-y-auto"
                >
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            :disabled="saving"
                            :class="[
                                'flex w-full gap-3 px-4 py-3.5 text-left transition-colors hover:bg-blue-50 disabled:cursor-not-allowed',
                                item.read_at ? 'bg-white' : 'bg-blue-50/60',
                            ]"
                            @click="openNotification(item)"
                        >
                            <span
                                class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl border border-explorer-line bg-white text-explorer-blue"
                                ><MessageSquare :size="15"
                            /></span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center justify-between gap-2 text-xs font-semibold"
                                    >{{ item.title
                                    }}<span
                                        v-if="!item.read_at"
                                        class="size-1.5 shrink-0 rounded-full bg-explorer-blue"
                                        aria-label="Unread"
                                    ></span
                                ></span>
                                <span
                                    class="mt-1 block text-[11px] leading-5 text-slate-600"
                                    >{{ item.message }}</span
                                >
                                <time
                                    :datetime="item.created_at"
                                    class="mt-1 block text-[10px] text-slate-400"
                                    >{{ dateLabel(item.created_at) }}</time
                                >
                            </span>
                        </button>
                    </li>
                </ul>
                <div
                    v-if="total > 10"
                    class="flex items-center justify-between border-t border-explorer-line px-4 py-2"
                >
                    <button
                        type="button"
                        aria-label="Previous notifications"
                        :disabled="loading || saving || currentPage <= 1"
                        class="grid size-9 place-items-center rounded-lg border border-explorer-line hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="loadPage(currentPage - 1)"
                    >
                        <ChevronLeft :size="15" />
                    </button>
                    <span class="text-[10px] text-slate-500"
                        >{{ currentPage }} / {{ lastPage }}</span
                    >
                    <button
                        type="button"
                        aria-label="Next notifications"
                        :disabled="loading || saving || currentPage >= lastPage"
                        class="grid size-9 place-items-center rounded-lg border border-explorer-line hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="loadPage(currentPage + 1)"
                    >
                        <ChevronRight :size="15" />
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
