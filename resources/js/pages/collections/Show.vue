<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FolderHeart, Globe2, MapPin } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import BusinessResults from '@/components/businesses/BusinessResults.vue';
import { show as businessShow } from '@/routes/businesses';
import { show } from '@/routes/collections';
import { index } from '@/routes/explorer';
import type { PublicCollection, PublicCollectionPlacesPage } from '@/types';

const props = defineProps<{
    collection: PublicCollection;
    places: PublicCollectionPlacesPage;
}>();
const loading = ref(false);
const error = ref('');
let requestedPage = props.places.meta.current_page;
let cancel: (() => void) | undefined;

function handleSelect(id: number) {
    const business = props.places.data.find((place) => place.id === id);

    if (business) {
        router.visit(businessShow.url(business.slug));
    }
}

function handleFailure() {
    error.value = 'Could not load that page. Please try again.';

    return false;
}

function handlePage(page: number) {
    requestedPage = page;
    loading.value = true;
    error.value = '';
    router.get(
        show.url(props.collection.share_token),
        { page },
        {
            preserveState: true,
            preserveScroll: true,
            onCancelToken: (token) => {
                cancel = () => token.cancel();
            },
            onError: handleFailure,
            onHttpException: handleFailure,
            onNetworkError: handleFailure,
            onFinish: () => {
                loading.value = false;
                cancel = undefined;
            },
        },
    );
}

onUnmounted(() => cancel?.());
</script>

<template>
    <Head :title="collection.name">
        <meta
            head-key="description"
            name="description"
            :content="`${collection.name}: a shared collection of local places on BizMapper.`"
        />
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
        <link head-key="canonical" rel="canonical" :href="collection.url" />
    </Head>
    <main
        class="min-h-dvh bg-explorer-cloud font-explorer-body text-explorer-ink scheme-light [&_:focus-visible]:outline-3 [&_:focus-visible]:outline-offset-3 [&_:focus-visible]:outline-explorer-blue [&_button]:cursor-pointer [&_button:disabled]:cursor-not-allowed"
    >
        <header class="border-b border-explorer-line bg-white">
            <nav
                aria-label="Collection navigation"
                class="mx-auto flex h-18 max-w-3xl items-center justify-between gap-4 px-5"
            >
                <Link
                    :href="index.url()"
                    aria-label="BizMapper home"
                    class="flex items-center gap-2 font-explorer-heading text-lg font-bold"
                    ><span
                        class="grid size-9 place-items-center rounded-xl bg-explorer-blue text-white"
                        ><MapPin :size="20" /></span
                    ><span
                        >biz<span class="text-explorer-blue">mapper</span></span
                    ></Link
                >
                <Link
                    :href="index.url()"
                    class="inline-flex h-9 items-center gap-2 text-xs font-semibold text-explorer-blue"
                    ><ArrowLeft :size="14" /> Explore nearby</Link
                >
            </nav>
        </header>
        <article class="mx-auto max-w-3xl px-4 py-6 sm:px-5 sm:py-10">
            <header
                class="rounded-2xl border border-explorer-line bg-white p-5 sm:p-7"
            >
                <span
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-2 py-1 text-[10px] font-semibold text-explorer-blue"
                    ><Globe2 :size="12" /> Public collection</span
                >
                <h1
                    class="mt-4 font-explorer-heading text-2xl font-semibold tracking-tight break-words sm:text-3xl"
                >
                    {{ collection.name }}
                </h1>
                <p
                    v-if="collection.description"
                    class="mt-3 text-xs leading-6 whitespace-pre-line text-slate-500"
                >
                    {{ collection.description }}
                </p>
                <p class="mt-3 text-xs leading-6 text-slate-500">
                    A shared list of local places. {{ places.meta.total }}
                    {{
                        places.meta.total === 1
                            ? 'business is'
                            : 'businesses are'
                    }}
                    available to explore.
                </p>
                <Link
                    :href="
                        index.url({
                            query: {
                                collection: collection.share_token,
                                collection_page: places.meta.current_page,
                            },
                        })
                    "
                    class="mt-5 inline-flex h-9 items-center gap-2 rounded-xl bg-explorer-blue px-4 text-xs font-semibold text-white hover:bg-blue-700"
                    ><MapPin :size="15" /> Explore on map</Link
                >
            </header>
            <section
                class="mt-5 rounded-2xl border border-explorer-line bg-white pt-5"
                aria-label="Collection places"
            >
                <div class="px-5 pb-4">
                    <h2 class="text-sm font-semibold">In this collection</h2>
                    <p class="mt-2 text-[11px] leading-6 text-slate-500">
                        Choose a business to view its profile. Unavailable
                        listings are not shown.
                    </p>
                </div>
                <p
                    v-if="error"
                    role="alert"
                    class="mx-5 mb-4 rounded-xl bg-red-50 p-3 text-xs text-red-600"
                >
                    {{ error }}
                    <button
                        type="button"
                        class="h-9 underline"
                        @click="handlePage(requestedPage)"
                    >
                        Retry
                    </button>
                </p>
                <BusinessResults
                    v-if="places.data.length"
                    :places="places.data"
                    :pagination="places.meta"
                    :loading="loading"
                    @select="handleSelect"
                    @page-change="handlePage"
                />
                <div v-else class="px-5 pt-5 pb-10 text-center">
                    <FolderHeart
                        :size="28"
                        class="mx-auto mb-3 text-explorer-blue"
                    />
                    <h3 class="text-[13px] font-semibold">
                        No available places right now
                    </h3>
                    <p class="mt-2 text-xs leading-6 text-slate-500">
                        This collection has no available businesses to display.
                    </p>
                </div>
            </section>
        </article>
    </main>
</template>
