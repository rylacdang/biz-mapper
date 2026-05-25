<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { dashboard, login, register, logout } from '@/routes';
import type { Category, Business } from '@/types';

defineProps<{
    isOwner: boolean;
    canRegister: boolean;
    categories: Category[];
    myBusinesses: Business[];
    selectedCategory: string | number;
    activeLayer: string;
}>();

const emit = defineEmits<{
    (e: 'filterCategory', categoryId: string | number): void;
    (e: 'toggleLayer', layer: string): void;
    (e: 'panTo', payload: { lat: number; lng: number }): void;
    (e: 'openCreateModal'): void;
}>();
</script>

<template>
    <aside
        class="pointer-events-auto absolute top-4 bottom-4 left-4 z-10 flex max-h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] flex-col gap-6 overflow-y-auto rounded-2xl border border-gray-500/30 bg-gray-900/80 p-6 shadow-[0_20px_50px_rgba(0,0,0,0.5)] backdrop-blur-xl sm:w-96"
    >
        <!-- Branding Header -->
        <div
            class="flex items-center justify-between border-b border-gray-500/10 pb-4"
        >
            <div class="flex items-center gap-3">
                <div class="relative h-10 w-10 flex-shrink-0">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-xl bg-accent-light opacity-25"
                    ></span>
                    <img
                        src="/img/biz-mapper.png"
                        alt="BizMapper Logo"
                        class="relative z-10 h-full w-full object-contain"
                    />
                </div>
                <div>
                    <h1
                        class="bg-gradient-to-r from-gray-100 to-primary-light bg-clip-text text-xl font-extrabold tracking-tight text-transparent"
                    >
                        BizMapper
                    </h1>
                    <p
                        class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase"
                    >
                        {{
                            isOwner ? 'Owner Dashboard' : 'Hyperlocal Discovery'
                        }}
                    </p>
                </div>
            </div>
            <!-- Sign Out if logged in as owner -->
            <Link
                v-if="isOwner"
                :href="logout()"
                method="post"
                as="button"
                class="rounded-xl border border-gray-500/30 bg-gray-900 px-3 py-2 text-xs font-bold text-gray-100 transition-all hover:bg-gray-900/70 hover:text-white"
            >
                Sign Out
            </Link>
        </div>

        <!-- OWNER-SPECIFIC SIDEBAR PANEL -->
        <template v-if="isOwner">
            <!-- Stats/Performance Cards -->
            <div class="grid grid-cols-2 gap-3">
                <div
                    class="rounded-xl border border-gray-500/20 bg-gray-900/30 p-3"
                >
                    <span
                        class="block text-lg leading-none font-extrabold text-gray-100"
                        >{{ myBusinesses.length }}</span
                    >
                    <span
                        class="mt-1.5 block text-[9px] font-semibold tracking-wider text-gray-500 uppercase"
                        >My Listings</span
                    >
                </div>
                <div
                    class="rounded-xl border border-gray-500/20 bg-gray-900/30 p-3"
                >
                    <span
                        class="block text-lg leading-none font-extrabold text-primary-light"
                        >Active</span
                    >
                    <span
                        class="mt-1.5 block text-[9px] font-semibold tracking-wider text-gray-500 uppercase"
                        >Listing Status</span
                    >
                </div>
            </div>

            <!-- Add Listing Main Action Button -->
            <button
                @click="emit('openCreateModal')"
                class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-center text-xs font-bold text-white shadow-lg shadow-primary/10 transition-all hover:bg-primary-light active:scale-98"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Register New Business
            </button>

            <!-- Dynamic Category Chips Filter -->
            <div class="space-y-3 border-t border-gray-500/20 pt-4">
                <h3
                    class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                >
                    Map Category Filter
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button
                        @click="emit('filterCategory', 'all')"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="[
                            selectedCategory === 'all'
                                ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                        ]"
                    >
                        All Services
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="emit('filterCategory', cat.id)"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="[
                            selectedCategory === cat.id
                                ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                        ]"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-3 w-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path :d="cat.icon" />
                        </svg>
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Owner Locations Manager Section -->
            <div
                class="flex-1 space-y-3 overflow-y-auto border-t border-gray-500/20 pt-4"
            >
                <h3
                    class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                >
                    Manage My Locations
                </h3>
                <div
                    v-if="myBusinesses.length === 0"
                    class="rounded-xl border border-dashed border-gray-500/20 bg-gray-900/30 p-6 text-center"
                >
                    <p class="text-xs leading-relaxed text-gray-500">
                        No registered locations found. Click the button above to
                        add your first business listing.
                    </p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="biz in myBusinesses"
                        :key="biz.id"
                        @click="emit('panTo', { lat: biz.lat, lng: biz.lng })"
                        class="group flex cursor-pointer items-center gap-3 rounded-xl border border-gray-500/20 bg-gray-900/30 p-2.5 transition duration-200 hover:border-gray-500/40 hover:bg-gray-900/50"
                    >
                        <div
                            class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg bg-gray-800"
                        >
                            <img
                                :src="
                                    biz.cover_photo
                                        ? '/storage/' + biz.cover_photo
                                        : '/img/placeholder.jpg'
                                "
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4
                                class="truncate text-xs font-bold text-gray-200 group-hover:text-white"
                            >
                                {{ biz.name }}
                            </h4>
                            <p
                                class="mt-0.5 truncate text-[10px] text-gray-500"
                            >
                                {{ biz.address }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- GUEST / CONSUMER SIDEBAR PANEL -->
        <template v-else>
            <!-- Intro / Tagline -->
            <div class="space-y-2">
                <h2 class="text-base font-bold text-gray-100">
                    Map Local Businesses Across The Philippines
                </h2>
                <p class="text-xs leading-relaxed text-gray-500">
                    Explore real-time reviews, AI sentiments, and discover
                    unique local offerings via interactive geospatial
                    visualizer.
                </p>
            </div>

            <!-- Sleek Input Concept Search -->
            <div class="relative">
                <input
                    type="text"
                    placeholder="Search cafes, shops, hotels..."
                    class="w-full rounded-xl border border-gray-500/30 bg-gray-900/40 px-4 py-2.5 text-xs text-gray-100 placeholder-gray-500 transition-all duration-300 focus:border-primary focus:ring-2 focus:ring-primary/50 focus:outline-none"
                />
                <span class="absolute top-3 right-3.5 text-gray-500">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="M21 21l-4.35-4.35" />
                    </svg>
                </span>
            </div>

            <!-- Category Chips Filter -->
            <div class="space-y-3">
                <h3
                    class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
                >
                    Filter by Category
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="emit('filterCategory', cat.id)"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="[
                            selectedCategory === cat.id
                                ? 'border-gray-100 bg-gray-100 text-gray-900 shadow-lg shadow-gray-100/10'
                                : 'border-gray-500/20 bg-gray-900/30 text-gray-500 hover:border-gray-500/40 hover:text-gray-100',
                        ]"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-3 w-3"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path :d="cat.icon" />
                        </svg>
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Stats/Info panel -->
            <div
                class="mt-auto grid grid-cols-2 gap-4 border-t border-gray-500/20 pt-4"
            >
                <div>
                    <span class="block text-xs font-extrabold text-gray-100"
                        >12,879+</span
                    >
                    <span
                        class="text-[9px] font-semibold text-gray-500 uppercase"
                        >Active listings</span
                    >
                </div>
                <div>
                    <span class="block text-xs font-extrabold text-gray-100"
                        >48,290+</span
                    >
                    <span
                        class="text-[9px] font-semibold text-gray-500 uppercase"
                        >AI review outputs</span
                    >
                </div>
            </div>

            <!-- Auth Buttons inside Sidebar Footer -->
            <nav class="flex flex-col gap-2 border-t border-gray-500/20 pt-4">
                <Link
                    v-if="$page.props.auth?.user"
                    :href="dashboard()"
                    class="w-full cursor-pointer rounded-xl bg-primary py-2.5 text-center text-xs font-bold text-white shadow-lg shadow-primary/10 transition-all hover:bg-primary-light"
                >
                    Access Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="w-full cursor-pointer rounded-xl border border-gray-500/30 bg-gray-900 py-2.5 text-center text-xs font-bold text-gray-100 transition-all hover:bg-gray-900/70 hover:text-white"
                    >
                        Sign In
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="w-full cursor-pointer rounded-xl bg-gray-100 py-2.5 text-center text-xs font-bold text-gray-900 shadow-md shadow-gray-100/5 transition-all hover:bg-white"
                    >
                        Register Business
                    </Link>
                </template>
            </nav>
        </template>

        <!-- Map Layer Switcher inside Sidebar -->
        <div class="space-y-3 border-t border-gray-500/20 pt-4">
            <h3
                class="text-[10px] font-bold tracking-widest text-gray-500 uppercase"
            >
                Map View Mode
            </h3>
            <div
                class="grid grid-cols-2 gap-2 rounded-xl border border-gray-500/20 bg-gray-900/30 p-1"
            >
                <button
                    @click="emit('toggleLayer', 'satellite')"
                    class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                    :class="
                        activeLayer === 'satellite'
                            ? 'bg-primary text-white shadow'
                            : 'text-gray-500 hover:text-gray-100'
                    "
                >
                    Satellite
                </button>
                <button
                    @click="emit('toggleLayer', 'street')"
                    class="cursor-pointer rounded-lg py-1.5 text-xs font-bold transition-all duration-200"
                    :class="
                        activeLayer === 'street'
                            ? 'bg-primary text-white shadow'
                            : 'text-gray-500 hover:text-gray-100'
                    "
                >
                    Streets
                </button>
            </div>
        </div>
    </aside>
</template>
