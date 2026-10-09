<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessGallery from '@/components/businesses/BusinessGallery.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import { DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import type { SearchBusiness } from '@/types';
import { searchParams } from '@/utils/search';

const props = defineProps<{
    business: SearchBusiness;
    metadata: {
        title: string;
        description: string;
        url: string;
        image: string | null;
    };
}>();

const explorerUrl = computed(
    () =>
        `/?${searchParams({
            lat: props.business.lat,
            lng: props.business.lng,
            radius: DEFAULT_SEARCH_RADIUS,
            keyword: '',
            category: 'all',
        })}`,
);
</script>

<template>
    <Head :title="business.name">
        <meta
            head-key="description"
            name="description"
            :content="metadata.description"
        />
        <link head-key="canonical" rel="canonical" :href="metadata.url" />
        <meta
            head-key="og:title"
            property="og:title"
            :content="metadata.title"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="metadata.description"
        />
        <meta head-key="og:url" property="og:url" :content="metadata.url" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta
            v-if="metadata.image"
            head-key="og:image"
            property="og:image"
            :content="metadata.image"
        />
    </Head>

    <main
        class="min-h-dvh bg-explorer-cloud font-explorer-body text-explorer-ink scheme-light [&_:focus-visible]:outline-3 [&_:focus-visible]:outline-offset-4 [&_:focus-visible]:outline-explorer-blue [&_button]:cursor-pointer"
    >
        <!-- Profile navigation stays separate from account and owner tools -->
        <header class="border-b border-explorer-line bg-white">
            <nav
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
                aria-label="Business profile navigation"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2.5 font-explorer-heading text-xl font-bold tracking-tight"
                    aria-label="BizMapper home"
                >
                    <span
                        class="grid size-9 place-items-center rounded-xl bg-explorer-blue text-white"
                    >
                        <MapPin :size="22" />
                    </span>
                    <span
                        >biz<span class="text-explorer-blue">mapper</span></span
                    >
                </Link>

                <Link
                    :href="explorerUrl"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-explorer-blue"
                >
                    <ArrowLeft :size="16" />
                    Explore nearby
                </Link>
            </nav>
        </header>

        <!-- Photo-led public profile -->
        <article class="mx-auto max-w-6xl px-5 pt-7 pb-16 sm:px-8 sm:pt-10">
            <BusinessIdentity :place="business" full-page />

            <div
                class="mt-8 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-14"
            >
                <div class="space-y-8">
                    <BusinessGallery
                        :photos="business.gallery_photos"
                        :name="business.name"
                    />
                    <section aria-label="About this business">
                        <h2 class="font-explorer-heading text-xl font-semibold">
                            A little about this place
                        </h2>
                        <p
                            class="mt-4 text-sm leading-8 whitespace-pre-line text-slate-600 sm:text-base"
                        >
                            {{
                                business.description ||
                                'The owner hasn’t added a description yet.'
                            }}
                        </p>
                    </section>

                    <section
                        class="border-t border-explorer-line pt-6"
                        aria-label="Business contact details"
                    >
                        <h2 class="font-explorer-heading text-xl font-semibold">
                            Find them here
                        </h2>
                        <BusinessContact :place="business" />

                        <Link
                            :href="explorerUrl"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-explorer-blue hover:underline"
                        >
                            Explore this neighborhood
                            <ArrowUpRight :size="16" />
                        </Link>
                    </section>
                </div>

                <!-- Visit and share actions remain easy to find on small screens -->
                <aside
                    class="order-first space-y-7 rounded-2xl border border-explorer-line bg-white p-6 lg:sticky lg:top-6 lg:order-last"
                    aria-label="Plan your visit"
                >
                    <div>
                        <h2 class="font-explorer-heading text-lg font-semibold">
                            Worth a visit?
                        </h2>
                        <p
                            class="mt-2 mb-5 text-sm leading-relaxed text-slate-500"
                        >
                            Find your way there or share this place with
                            someone.
                        </p>
                        <BusinessActions :place="business" />
                    </div>

                    <BusinessHours :place="business" />
                </aside>
            </div>
        </article>
    </main>
</template>
