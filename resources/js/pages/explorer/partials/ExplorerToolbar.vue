<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bookmark,
    Search,
    SlidersHorizontal,
    UserRound,
    Store,
    Plus,
    List,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { login, logout, register } from '@/routes';

defineProps<{
    count: number;
    loading: boolean;
    owner: boolean;
    panelOpen: boolean;
    canRegister: boolean;
}>();

const keyword = defineModel<string>('keyword', { required: true });

const emit = defineEmits<{
    search: [];
    explore: [];
    saved: [];
    reviews: [];
    listings: [];
    create: [];
}>();

const page = usePage();
const accountMenu = ref<HTMLDetailsElement>();

// Return focus to the menu opener before opening another panel or drawer.
function closeAccountMenu() {
    if (accountMenu.value) {
        accountMenu.value.open = false;
        accountMenu.value.querySelector('summary')?.focus();
    }
}

function handleOpenListings() {
    closeAccountMenu();
    emit('listings');
}

function handleOpenSaved() {
    closeAccountMenu();
    emit('saved');
}

function handleOpenReviews() {
    closeAccountMenu();
    emit('reviews');
}

function handleCreateListing() {
    closeAccountMenu();
    emit('create');
}
</script>

<template>
    <!-- Compact branding -->
    <Link
        href="/"
        aria-label="BizMapper home"
        :class="[
            'absolute top-4 left-4 z-5 flex h-12 items-center gap-2.5 rounded-2xl bg-white px-3',
            'shadow-lg shadow-explorer-ink/10 max-md:left-3 max-md:w-11 max-md:justify-center',
            'max-md:px-0',
        ]"
    >
        <AppLogoIcon class="size-8 shrink-0 object-contain" />

        <span
            class="flex font-explorer-heading text-lg font-bold tracking-tight max-md:hidden"
        >
            <span>biz</span>

            <span class="text-explorer-blue">mapper</span>
        </span>
    </Link>

    <!-- Floating keyword search -->
    <form
        :class="[
            'absolute top-4 left-[200px] z-5 flex h-12 w-[360px] items-center gap-2 rounded-2xl border',
            'border-white bg-white p-1.5 pl-3.5 shadow-lg shadow-explorer-ink/10',
            'max-md:left-[64px] max-md:w-auto',
            page.props.auth?.user
                ? 'max-md:right-[112px]'
                : 'max-md:right-[64px]',
        ]"
        aria-label="Find local businesses"
        @submit.prevent="emit('search')"
    >
        <Search class="shrink-0 text-slate-400" :size="18" />

        <input
            v-model="keyword"
            class="min-w-0 flex-1 bg-transparent text-xs outline-none placeholder:text-slate-400"
            aria-label="Search businesses"
            placeholder="Find a place nearby…"
        />

        <button
            type="button"
            aria-label="Open filters and results"
            class="grid size-8 shrink-0 place-items-center rounded-xl text-slate-500 hover:bg-slate-100"
            @click="emit('explore')"
        >
            <SlidersHorizontal :size="17" />
        </button>

        <button
            type="submit"
            aria-label="Search nearby"
            :disabled="loading"
            class="grid size-8 shrink-0 place-items-center rounded-xl bg-explorer-blue text-white max-md:hidden"
        >
            <Search :size="16" />
        </button>
    </form>

    <!-- Account and owner actions -->
    <div
        class="absolute top-4 right-4 z-6 flex h-12 items-center gap-3 max-md:right-3"
    >
        <NotificationBell />
        <details ref="accountMenu" class="relative">
            <summary
                aria-label="Account menu"
                :class="[
                    'flex h-12 cursor-pointer list-none items-center gap-2 rounded-2xl bg-white px-3.5 text-xs',
                    'font-semibold shadow-lg shadow-explorer-ink/10 max-md:w-11 max-md:justify-center',
                    'max-md:px-0 [&::-webkit-details-marker]:hidden',
                ]"
            >
                <UserRound :size="18" />

                <span class="max-md:hidden">
                    {{ page.props.auth?.user ? 'My account' : 'Account' }}
                </span>
            </summary>

            <nav
                aria-label="Account actions"
                :class="[
                    'absolute top-14 right-0 flex w-52 flex-col gap-1 rounded-2xl border border-slate-100',
                    'bg-white p-2 text-xs shadow-xl shadow-explorer-ink/15 [&_a]:rounded-xl [&_a]:px-3',
                    '[&_a]:py-3 [&_a:hover]:bg-slate-50 [&_button]:rounded-xl [&_button]:px-3 [&_button]:py-3',
                    '[&_button]:text-left [&_button:hover]:bg-slate-50',
                ]"
            >
                <template v-if="page.props.auth?.user">
                    <p class="px-3 py-2 text-slate-400">
                        {{ page.props.auth.user.name }}
                    </p>

                    <button v-if="owner" @click="handleOpenListings">
                        My listings
                    </button>

                    <button v-if="owner" @click="handleCreateListing">
                        Add a business
                    </button>

                    <button
                        v-if="page.props.auth.user.role === 'consumer'"
                        @click="handleOpenSaved"
                    >
                        Saved places
                    </button>

                    <button
                        v-if="page.props.auth.user.role === 'consumer'"
                        @click="handleOpenReviews"
                    >
                        My reviews
                    </button>

                    <Link href="/settings/profile">Profile and settings</Link>

                    <Link :href="logout()" as="button"> Log out </Link>
                </template>

                <template v-else>
                    <Link :href="login()">Log in</Link>

                    <Link v-if="canRegister" :href="register()">
                        Join the neighborhood
                    </Link>
                </template>
            </nav>
        </details>
    </div>

    <!-- Panel shortcuts remain visible while the map is unobstructed -->
    <nav
        v-if="!panelOpen"
        aria-label="Explore tools"
        class="absolute bottom-6 left-4 z-4 flex gap-2 max-md:right-14 max-md:bottom-[calc(24px+env(safe-area-inset-bottom))] max-md:left-3"
    >
        <button
            class="flex h-12 items-center gap-2.5 rounded-2xl border border-white bg-white px-4 text-xs font-semibold shadow-lg shadow-explorer-ink/10 max-md:gap-1.5 max-md:px-2 max-md:text-[11px] max-md:whitespace-nowrap"
            @click="emit('explore')"
        >
            <List class="text-explorer-blue" :size="18" />
            Explore places
            <span
                class="rounded-lg bg-slate-100 px-2 py-1 text-[10px] text-slate-500 max-md:px-1.5"
            >
                {{ count }}
            </span>
        </button>

        <button
            v-if="owner"
            class="flex h-12 items-center gap-2 rounded-2xl border border-white bg-white px-4 text-xs font-semibold shadow-lg shadow-explorer-ink/10 max-md:gap-1.5 max-md:px-2 max-md:text-[11px] max-md:whitespace-nowrap"
            @click="emit('listings')"
        >
            <Store :size="17" />
            My listings
        </button>

        <button
            v-if="page.props.auth?.user?.role === 'consumer'"
            class="flex h-12 items-center gap-2 rounded-2xl border border-white bg-white px-4 text-xs font-semibold shadow-lg shadow-explorer-ink/10 max-md:gap-1.5 max-md:px-2 max-md:text-[11px] max-md:whitespace-nowrap"
            @click="handleOpenSaved"
        >
            <Bookmark :size="17" class="text-explorer-blue" /> Saved places
        </button>

        <button
            v-if="owner"
            aria-label="Add a business"
            class="grid size-12 place-items-center rounded-2xl bg-explorer-blue text-white shadow-lg shadow-explorer-blue/20 max-md:hidden"
            @click="emit('create')"
        >
            <Plus :size="20" />
        </button>
    </nav>
</template>
