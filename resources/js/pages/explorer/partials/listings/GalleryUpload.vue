<script setup lang="ts">
import { ArrowLeft, ArrowRight, ImagePlus, Trash2 } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';
import type { GalleryPhotoDraft } from '@/types';
import { photoUrl } from '@/utils/businesses';

const photos = defineModel<GalleryPhotoDraft[]>({ required: true });
const props = defineProps<{ errors: string[]; disabled: boolean }>();
const input = ref<HTMLInputElement>();
const uploadError = ref('');
const previews = new Map<File, string>();

// Release previews as soon as a draft photo is removed, or the drawer closes.
watch(
    () => photos.value.map((photo) => photo.file),
    (files) => {
        for (const [file, url] of previews) {
            if (!files.includes(file)) {
                URL.revokeObjectURL(url);
                previews.delete(file);
            }
        }
    },
    { immediate: true },
);

onUnmounted(() => previews.forEach((url) => URL.revokeObjectURL(url)));

function thumbnail(photo: GalleryPhotoDraft): string {
    if (!photo.file) {
        return photoUrl(photo.path) ?? '';
    }

    if (!previews.has(photo.file)) {
        previews.set(photo.file, URL.createObjectURL(photo.file));
    }

    return previews.get(photo.file)!;
}

function handleUpload(event: Event) {
    const element = event.target as HTMLInputElement;
    const files = Array.from(element.files ?? []);
    element.value = '';
    uploadError.value = '';

    if (photos.value.length + files.length > 5) {
        uploadError.value = 'You can add up to 5 gallery photos.';

        return;
    }

    if (
        files.some(
            (file) =>
                !['image/jpeg', 'image/png', 'image/webp'].includes(
                    file.type,
                ) || file.size > 5 * 1024 * 1024,
        )
    ) {
        uploadError.value = 'Choose JPG, PNG or WebP images up to 5 MB each.';

        return;
    }

    photos.value = [
        ...photos.value,
        ...files.map((file) => ({ path: null, file })),
    ];
}

function handleMove(index: number, offset: number) {
    const next = [...photos.value];
    const target = index + offset;

    if (target < 0 || target >= next.length) {
        return;
    }

    [next[index], next[target]] = [next[target], next[index]];
    photos.value = next;
}

function handleRemove(index: number) {
    photos.value = photos.value.filter((_, position) => position !== index);
    uploadError.value = '';
}
</script>

<template>
    <section
        class="mt-6 space-y-3 border-t border-explorer-line pt-5"
        aria-label="Manage gallery photos"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-[11px] font-bold">
                    Gallery
                    <span class="ml-1 font-normal text-slate-400"
                        >Optional</span
                    >
                </h3>
                <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                    Show your shop, products or services. {{ photos.length }}/5
                    photos.
                </p>
            </div>
            <button
                type="button"
                :disabled="disabled || photos.length >= 5"
                class="inline-flex h-9 items-center gap-2 rounded-xl border border-explorer-line bg-white px-3 text-xs font-semibold text-explorer-blue hover:bg-explorer-cloud disabled:opacity-50"
                @click="input?.click()"
            >
                <ImagePlus :size="15" /> Add photos
            </button>
            <input
                ref="input"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp"
                multiple
                :disabled="disabled"
                aria-label="Choose gallery photos"
                @change="handleUpload"
            />
        </div>

        <p
            v-if="!photos.length"
            class="rounded-xl border border-dashed border-explorer-line bg-explorer-cloud px-4 py-5 text-center text-[11px] text-slate-500"
        >
            Add a few photos to help visitors recognize your business.
        </p>

        <ol v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <li
                v-for="(photo, index) in photos"
                :key="photo.path ?? `${photo.file?.name}-${index}`"
                class="overflow-hidden rounded-xl border border-explorer-line bg-white"
            >
                <img
                    :src="thumbnail(photo)"
                    :alt="`Gallery photo ${index + 1} preview`"
                    class="aspect-[4/3] w-full object-cover"
                />
                <div class="flex items-center justify-between gap-1 px-2 py-1">
                    <span class="text-[10px] text-slate-500">{{
                        index + 1
                    }}</span>
                    <div class="flex items-center">
                        <button
                            type="button"
                            :disabled="disabled || index === 0"
                            :aria-label="`Move photo ${index + 1} earlier`"
                            class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-explorer-cloud disabled:opacity-30"
                            @click="handleMove(index, -1)"
                        >
                            <ArrowLeft :size="14" />
                        </button>
                        <button
                            type="button"
                            :disabled="disabled || index === photos.length - 1"
                            :aria-label="`Move photo ${index + 1} later`"
                            class="grid size-9 place-items-center rounded-lg text-slate-500 hover:bg-explorer-cloud disabled:opacity-30"
                            @click="handleMove(index, 1)"
                        >
                            <ArrowRight :size="14" />
                        </button>
                        <button
                            type="button"
                            :disabled="disabled"
                            :aria-label="`Remove gallery photo ${index + 1}`"
                            class="grid size-9 place-items-center rounded-lg text-red-500 hover:bg-red-50"
                            @click="handleRemove(index)"
                        >
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>
            </li>
        </ol>

        <p class="text-[10px] text-slate-400">
            JPG, PNG or WebP · Up to 5 MB each. Use the arrows to change the
            display order.
        </p>
        <p v-if="uploadError" role="alert" class="text-[11px] text-red-600">
            {{ uploadError }}
        </p>
        <p
            v-for="message in props.errors"
            :key="message"
            role="alert"
            class="text-[11px] text-red-600"
        >
            {{ message }}
        </p>
    </section>
</template>
