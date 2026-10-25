<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, RotateCcw, Store, Eye } from 'lucide-vue-next';
import { show } from '@/routes/businesses';
import type { Business } from '@/types';
import { photoUrl } from '@/utils/businesses';

defineProps<{
    places: Business[];
    trash: Business[];
    showingTrash: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{
    create: [];
    edit: [listing: Business];
    toggleTrash: [];
    restore: [id: number];
}>();
</script>

<template>
    <!-- Active and deleted owner listings -->
    <section class="px-5 py-[25px] max-md:pt-4">
        <!-- Workspace heading and trash navigation -->
        <div class="flex items-center justify-between">
            <div>
                <span
                    class="block text-[8px] font-[800] tracking-[1.65px] text-[#8593a8]"
                >
                    YOUR LOCAL FOOTPRINT
                </span>

                <h2 class="mx-0 mt-[10px] mb-5 text-[23px] tracking-[-0.8px]">
                    {{ showingTrash ? 'Deleted listings' : 'My listings' }}
                </h2>
            </div>

            <button
                class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-[#f3f6fb] text-[#768ba5]"
                :aria-label="showingTrash ? 'Back to listings' : 'Open trash'"
                @click="emit('toggleTrash')"
            >
                <RotateCcw v-if="showingTrash" :size="18" />

                <Trash2 v-else :size="18" />
            </button>
        </div>

        <button
            v-if="!showingTrash"
            class="mb-[22px] inline-flex w-full items-center justify-center gap-2 rounded-[10px] border-0 bg-explorer-blue px-[17px] py-3 text-[12px] font-[700] text-white"
            @click="emit('create')"
        >
            <Plus :size="18" />
            Add a business
        </button>

        <!-- Listing management actions -->
        <div>
            <article
                v-for="place in showingTrash ? trash : places"
                :key="place.id"
                class="flex items-center gap-3 border-b border-[#edf1f6] px-0 py-4"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <img
                        class="h-15 w-14 shrink-0 rounded-[9px] object-cover"
                        v-if="photoUrl(place.cover_photo)"
                        :src="photoUrl(place.cover_photo)"
                        :alt="place.name"
                    />

                    <Store v-else :size="28" />

                    <div class="min-w-0 flex-1">
                        <h3 class="m-0 truncate text-[11px]">
                            {{ place.name }}
                        </h3>

                        <p class="mx-0 my-[3px] text-[10px] text-[#8e9bb0]">
                            {{ place.category?.name }}
                        </p>

                        <span
                            class="rounded-[4px] bg-[#eff5ff] px-[6px] py-[2px] text-[9px] text-[#6589c4]"
                        >
                            {{
                                showingTrash
                                    ? 'Deleted'
                                    : place.status === 'active'
                                      ? 'Active'
                                      : 'Pending'
                            }}
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        v-if="!showingTrash"
                        :href="show(place.slug).url"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] text-[#768ba5] transition-colors hover:bg-[#f3f6fb] hover:text-explorer-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-explorer-blue"
                        :aria-label="`View ${place.name} business profile`"
                        title="View business profile"
                    >
                        <Eye :size="18" aria-hidden="true" />
                    </Link>
                    <button
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[8px] border-0 bg-transparent text-[#768ba5] transition-colors hover:bg-[#f3f6fb] hover:text-explorer-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-explorer-blue"
                        :disabled="busy"
                        :aria-label="`${showingTrash ? 'Restore' : 'Edit'} ${place.name}`"
                        @click="
                            showingTrash
                                ? emit('restore', place.id)
                                : emit('edit', place)
                        "
                    >
                        <RotateCcw v-if="showingTrash" :size="18" />

                        <Pencil v-else :size="18" />
                    </button>
                </div>
            </article>
        </div>

        <!-- Empty owner workspace -->
        <div
            v-if="!(showingTrash ? trash : places).length"
            class="px-5 py-[35px] text-center text-[#8b9bb1]"
        >
            <Store class="mx-auto mt-0 mb-4 text-[#a4b9d4]" :size="32" />

            <h3 class="text-[13px] text-[#526b88]">
                {{
                    showingTrash
                        ? 'Trash is empty'
                        : 'Put your business on the map'
                }}
            </h3>

            <p class="text-[12px] leading-[1.7]">
                {{
                    showingTrash
                        ? 'Deleted listings will appear here.'
                        : 'Add your photos, details, and location to get started.'
                }}
            </p>
        </div>
    </section>
</template>
