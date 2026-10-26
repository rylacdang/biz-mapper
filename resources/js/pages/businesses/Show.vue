<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, Store } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import BusinessActions from '@/components/businesses/BusinessActions.vue';
import BusinessContact from '@/components/businesses/BusinessContact.vue';
import BusinessGallery from '@/components/businesses/BusinessGallery.vue';
import BusinessHours from '@/components/businesses/BusinessHours.vue';
import BusinessIdentity from '@/components/businesses/BusinessIdentity.vue';
import BusinessReviewSummary from '@/components/businesses/BusinessReviewSummary.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { DEFAULT_SEARCH_RADIUS } from '@/constants/map';
import BusinessCatalog from '@/pages/businesses/partials/BusinessCatalog.vue';
import BusinessReviews from '@/pages/businesses/partials/BusinessReviews.vue';
import DepthCarousel from '@/pages/businesses/partials/DepthCarousel.vue';
import type { SearchBusiness, OfferingPage, BusinessFeedback } from '@/types';
import { photoUrl } from '@/utils/businesses';
import { searchParams } from '@/utils/search';

const props = defineProps<{
    business: SearchBusiness;
    offerings: OfferingPage;
    feedback: BusinessFeedback;
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

const photos = computed(() => props.business.gallery_photos ?? []);
const coverFailed = ref(false);

watch(
    () => props.business.cover_photo,
    () => {
        coverFailed.value = false;
    },
);

const carouselItems = computed(() =>
    photos.value.map((photo, index) => ({
        image: photoUrl(photo) ?? '',
        alt: `${props.business.name} photo ${index + 1}`,
    })),
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
        class="min-h-dvh overflow-x-clip bg-explorer-cloud font-explorer-body text-explorer-ink scheme-light [&_:focus-visible]:outline-3 [&_:focus-visible]:outline-offset-4 [&_:focus-visible]:outline-explorer-blue [&_button]:cursor-pointer [&_button:disabled]:cursor-not-allowed"
    >
        <!-- Profile navigation stays separate from account and owner tools -->
        <header class="border-b border-explorer-line bg-white">
            <nav
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4 sm:px-8"
                aria-label="Business profile navigation"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2.5 font-explorer-heading text-lg font-bold tracking-tight"
                    aria-label="BizMapper home"
                >
                    <AppLogoIcon class="size-9 shrink-0 object-contain" />
                    <span
                        >biz<span class="text-explorer-blue">mapper</span></span
                    >
                </Link>

                <div class="flex items-center gap-3">
                    <NotificationBell />
                    <Link
                        :href="explorerUrl"
                        aria-label="Explore nearby"
                        class="inline-flex h-9 shrink-0 items-center gap-2 rounded-full border border-explorer-line px-3 text-[11px] font-semibold text-explorer-ink hover:bg-explorer-cloud"
                    >
                        <ArrowLeft :size="16" />
                        <span
                            >Explore<span class="hidden sm:inline">
                                nearby</span
                            ></span
                        >
                    </Link>
                </div>
            </nav>
        </header>

        <article class="mx-auto max-w-6xl px-5 pt-6 pb-12 sm:px-8">
            <!-- The cover belongs to the business header, separate from gallery uploads. -->
            <header class="rounded-3xl border border-explorer-line bg-white">
                <div
                    class="relative grid h-44 place-items-center overflow-hidden rounded-t-3xl bg-blue-50 sm:h-64 lg:h-72"
                >
                    <img
                        v-if="photoUrl(business.cover_photo) && !coverFailed"
                        :src="photoUrl(business.cover_photo)"
                        :alt="`${business.name} cover photo`"
                        class="h-full w-full object-cover"
                        @error="coverFailed = true"
                    />
                    <Store
                        v-else
                        :size="48"
                        class="text-blue-200"
                        aria-hidden="true"
                    />
                </div>
                <div
                    class="relative flex flex-col gap-5 px-5 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="min-w-0">
                        <BusinessIdentity
                            :place="business"
                            full-page
                            :show-cover="false"
                        />
                    </div>
                    <div class="w-full shrink-0 lg:w-auto">
                        <BusinessActions :place="business" />
                    </div>
                </div>
            </header>

            <BusinessCatalog :initial="offerings" :slug="business.slug" />

            <!-- Gallery uploads and practical details share the same reading area. -->
            <div
                class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,0.9fr)] lg:gap-10"
            >
                <section
                    aria-label="Business photos"
                    class="min-w-0 rounded-3xl border border-explorer-line bg-white p-4 sm:p-5"
                >
                    <div class="flex items-center justify-between gap-3 px-1">
                        <h2
                            class="font-explorer-heading text-base font-semibold"
                        >
                            A closer look
                        </h2>
                        <span
                            v-if="photos.length"
                            class="text-[11px] text-slate-400"
                            >{{ photos.length }}
                            {{ photos.length === 1 ? 'photo' : 'photos' }}</span
                        >
                    </div>
                    <BusinessGallery
                        v-if="photos.length"
                        :photos="photos"
                        :name="business.name"
                        :show-heading="false"
                    >
                        <template #default="{ openPhoto }"
                            ><DepthCarousel
                                :items="carouselItems"
                                :name="business.name"
                                @open="openPhoto"
                        /></template>
                    </BusinessGallery>
                    <div
                        v-else
                        class="flex min-h-40 flex-col items-center justify-center gap-3 text-slate-400"
                    >
                        <Store :size="28" />
                        <p class="text-xs">
                            Gallery photos havenâ€™t been added yet.
                        </p>
                    </div>
                </section>

                <div class="space-y-7">
                    <section aria-label="About this business">
                        <h2
                            class="font-explorer-heading text-base font-semibold"
                        >
                            About {{ business.name }}
                        </h2>
                        <p
                            class="mt-3 text-[13px] leading-6 whitespace-pre-line text-slate-600"
                        >
                            {{
                                business.description ||
                                'The owner has not added a description yet.'
                            }}
                        </p>
                    </section>

                    <section
                        aria-label="Plan your visit"
                        class="border-t border-explorer-line pt-6"
                    >
                        <h2
                            class="font-explorer-heading text-base font-semibold"
                        >
                            Plan your visit
                        </h2>
                        <BusinessContact :place="business" compact />
                        <Link
                            :href="explorerUrl"
                            class="mt-3 inline-flex h-9 items-center gap-2 rounded-xl border border-explorer-line bg-white px-3 text-[11px] font-semibold text-explorer-blue hover:bg-blue-50"
                        >
                            Explore this neighborhood
                            <ArrowUpRight :size="15" />
                        </Link>
                    </section>

                    <section
                        aria-label="Opening hours"
                        class="border-t border-explorer-line pt-6"
                    >
                        <BusinessHours :place="business" compact />
                    </section>
                </div>
            </div>
            <BusinessReviewSummary
                :business-slug="business.slug"
                :refresh-key="feedback"
                class="mt-8"
            />
            <BusinessReviews :initial="feedback" :business="business" />
        </article>
    </main>
</template>
