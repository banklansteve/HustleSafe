<template>
    <div>
        <Swiper
            v-if="files.length"
            :modules="modules"
            :slides-per-view="1.12"
            :space-between="12"
            :breakpoints="{
                640: { slidesPerView: 2.05, spaceBetween: 14 },
                1024: { slidesPerView: files.length >= 3 ? 2.85 : files.length, spaceBetween: 16 },
            }"
            :navigation="files.length > 1"
            :pagination="{ clickable: true, dynamicBullets: true }"
            class="quest-gallery-swiper rounded-2xl"
            @swiper="onMainSwiper"
        >
            <SwiperSlide v-for="(f, i) in files" :key="f.id">
                <div class="relative h-full">
                    <button
                        type="button"
                        class="group relative block w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-100 shadow-sm ring-1 ring-slate-100/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        @click="openLightbox(i)"
                    >
                        <template v-if="f.is_image">
                            <img
                                :src="f.url"
                                :alt="f.name"
                                class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.02]"
                                loading="lazy"
                            />
                        </template>
                        <template v-else>
                            <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 bg-gradient-to-b from-slate-50 to-white p-6 text-center">
                                <DocumentIcon class="h-10 w-10 text-primary-500" />
                                <p class="line-clamp-2 px-2 text-xs font-bold text-slate-800">
                                    {{ f.name }}
                                </p>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    PDF
                                </p>
                            </div>
                        </template>
                        <span
                            class="pointer-events-none absolute right-3 top-3 rounded-lg bg-slate-950/60 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white opacity-0 backdrop-blur-sm transition group-hover:opacity-100"
                        >
                            View
                        </span>
                    </button>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="absolute left-3 top-3 rounded-full bg-rose-600/95 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white shadow-lg ring-1 ring-white/30 transition hover:bg-rose-700"
                        @click.stop="$emit('delete', f.id)"
                    >
                        Remove
                    </button>
                </div>
            </SwiperSlide>
        </Swiper>
        <p
            v-else
            class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center text-sm font-semibold text-slate-500"
        >
            No files attached yet.
        </p>

        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="lightboxIndex !== null"
                    ref="lightboxRoot"
                    tabindex="-1"
                    class="fixed inset-0 z-[110] flex flex-col bg-slate-950/96 backdrop-blur-md outline-none"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Quest media viewer"
                    @click.self="closeLightbox"
                    @keydown.escape.prevent="closeLightbox"
                >
                    <div class="flex shrink-0 items-center justify-between gap-3 px-4 py-3 sm:px-6" @click.stop>
                        <p class="min-w-0 truncate text-sm font-bold text-white">
                            {{ activeFile?.name }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-black text-white ring-1 ring-white/25 transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="Close viewer"
                            @click="closeLightbox"
                        >
                            <span aria-hidden="true" class="text-lg leading-none">×</span>
                            Close
                        </button>
                    </div>
                    <div class="relative min-h-0 flex-1 px-2 pb-6 sm:px-6" @click.self="closeLightbox">
                        <Swiper
                            :key="'lb-' + lightboxIndex"
                            :modules="lightboxModules"
                            :initial-slide="lightboxIndex"
                            :navigation="files.length > 1"
                            :pagination="{ clickable: true, dynamicBullets: true }"
                            class="h-full w-full max-h-[calc(100vh-8rem)]"
                            @swiper="onBoxSwiper"
                        >
                            <SwiperSlide v-for="f in files" :key="'lb-' + f.id" class="!flex items-center justify-center">
                                <img
                                    v-if="f.is_image"
                                    :src="f.url"
                                    :alt="f.name"
                                    class="max-h-[calc(100vh-9rem)] max-w-full rounded-xl object-contain shadow-2xl ring-1 ring-white/10"
                                />
                                <a
                                    v-else
                                    :href="f.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-2xl bg-gradient-to-br from-primary-600 to-teal-700 px-8 py-4 text-sm font-bold text-white shadow-xl ring-1 ring-white/20"
                                >
                                    Open {{ f.name }}
                                </a>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                    <div class="pointer-events-none absolute inset-x-0 bottom-6 flex justify-center px-4">
                        <button
                            type="button"
                            class="pointer-events-auto inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-black text-slate-900 shadow-xl ring-2 ring-slate-900/10 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="closeLightbox"
                        >
                            <span class="text-lg leading-none" aria-hidden="true">×</span>
                            Close viewer
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { DocumentIcon } from '@heroicons/vue/24/outline';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { Navigation, Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    files: {
        type: Array,
        default: () => [],
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['delete']);

const modules = [Navigation, Pagination];
const lightboxModules = [Navigation, Pagination];

const mainSwiper = ref(null);
const boxSwiper = ref(null);
const lightboxIndex = ref(null);
const lightboxRoot = ref(null);

const activeFile = computed(() => {
    if (lightboxIndex.value === null) {
        return null;
    }

    return props.files[lightboxIndex.value] ?? null;
});

function onMainSwiper(swiper) {
    mainSwiper.value = swiper;
}

function onBoxSwiper(swiper) {
    boxSwiper.value = swiper;
}

function openLightbox(i) {
    lightboxIndex.value = i;
    document.body.style.overflow = 'hidden';
}

watch(lightboxIndex, async (v) => {
    if (v === null) {
        return;
    }
    await nextTick();
    lightboxRoot.value?.focus?.();
});

function closeLightbox() {
    lightboxIndex.value = null;
    document.body.style.overflow = '';
}

onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.quest-gallery-swiper :deep(.swiper-button-prev),
.quest-gallery-swiper :deep(.swiper-button-next) {
    height: 2.25rem;
    width: 2.25rem;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.95);
    color: rgb(15 118 110);
    box-shadow: 0 10px 25px rgb(15 23 42 / 0.12);
}

.quest-gallery-swiper :deep(.swiper-button-prev::after),
.quest-gallery-swiper :deep(.swiper-button-next::after) {
    font-size: 0.65rem;
    font-weight: 900;
}
</style>
