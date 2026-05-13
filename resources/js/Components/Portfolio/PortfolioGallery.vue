<template>
    <div>
        <Swiper
            :modules="modules"
            :slides-per-view="1.15"
            :space-between="12"
            :breakpoints="{
                640: { slidesPerView: 1.35, spaceBetween: 16 },
                1024: { slidesPerView: 1.5, spaceBetween: 20 },
            }"
            :navigation="true"
            :pagination="{ clickable: true }"
            class="portfolio-main-swiper rounded-xl"
            @swiper="onMainSwiper"
        >
            <SwiperSlide v-for="(f, i) in files" :key="f.id">
                <button
                    type="button"
                    class="group relative block w-full overflow-hidden rounded-xl border border-slate-200/90 bg-slate-100 ring-1 ring-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    @click="openLightbox(i)"
                >
                    <template v-if="f.is_image">
                        <img
                            :src="f.url"
                            :alt="f.original_name"
                            class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.02]"
                            loading="lazy"
                        />
                    </template>
                    <template v-else-if="f.is_video">
                        <video
                            :src="f.url"
                            class="aspect-[4/3] w-full object-cover"
                            muted
                            playsinline
                            preload="metadata"
                        />
                        <span
                            class="absolute inset-0 flex items-center justify-center bg-slate-900/25 text-white transition group-hover:bg-slate-900/35"
                        >
                            <span class="rounded-full bg-white/95 px-4 py-2 text-xs font-bold text-slate-900 shadow-lg">
                                Play in viewer
                            </span>
                        </span>
                    </template>
                    <template v-else>
                        <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 bg-slate-50 p-6 text-center">
                            <DocumentIcon class="h-10 w-10 text-slate-400" />
                            <p class="text-xs font-bold text-slate-700">
                                {{ f.original_name }}
                            </p>
                            <p class="text-[10px] font-semibold text-slate-500">
                                Tap to open
                            </p>
                        </div>
                    </template>
                    <span
                        class="pointer-events-none absolute right-3 top-3 rounded-lg bg-black/55 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white opacity-0 backdrop-blur-sm transition group-hover:opacity-100"
                    >
                        Expand
                    </span>
                </button>
            </SwiperSlide>
        </Swiper>

        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="lightboxIndex !== null"
                    tabindex="-1"
                    class="fixed inset-0 z-[110] flex flex-col bg-slate-950/95 backdrop-blur-md outline-none"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Media viewer"
                    @keydown.escape.prevent="closeLightbox"
                >
                    <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                        <p class="truncate text-sm font-bold text-white">
                            {{ activeFile?.original_name }}
                        </p>
                        <button
                            type="button"
                            class="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            @click="closeLightbox"
                        >
                            Close
                        </button>
                    </div>
                    <div class="relative min-h-0 flex-1 px-2 pb-6 sm:px-6">
                        <Swiper
                            :key="'lightbox-' + lightboxIndex"
                            :modules="lightboxModules"
                            :initial-slide="lightboxIndex"
                            :navigation="true"
                            :pagination="{ clickable: true }"
                            class="h-full w-full max-h-[calc(100vh-8rem)]"
                            @swiper="onBoxSwiper"
                        >
                            <SwiperSlide v-for="f in files" :key="'lb-' + f.id" class="!flex items-center justify-center">
                                <img
                                    v-if="f.is_image"
                                    :src="f.url"
                                    :alt="f.original_name"
                                    class="max-h-[calc(100vh-9rem)] max-w-full rounded-lg object-contain shadow-2xl"
                                />
                                <video
                                    v-else-if="f.is_video"
                                    :src="f.url"
                                    class="max-h-[calc(100vh-9rem)] max-w-full rounded-lg bg-black shadow-2xl"
                                    controls
                                    playsinline
                                />
                                <a
                                    v-else
                                    :href="f.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="rounded-xl bg-white px-6 py-4 text-sm font-bold text-primary-700 shadow-xl"
                                >
                                    Download {{ f.original_name }}
                                </a>
                            </SwiperSlide>
                        </Swiper>
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
import { computed, onUnmounted, ref } from 'vue';

const props = defineProps({
    files: {
        type: Array,
        required: true,
    },
});

const modules = [Navigation, Pagination];
const lightboxModules = [Navigation, Pagination];

const lightboxIndex = ref(null);
const mainSwiper = ref(null);
const boxSwiper = ref(null);

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
    document.body.classList.add('overflow-hidden');
}

function closeLightbox() {
    lightboxIndex.value = null;
    document.body.classList.remove('overflow-hidden');
}

onUnmounted(() => {
    document.body.classList.remove('overflow-hidden');
});
</script>

<style scoped>
.portfolio-main-swiper :deep(.swiper-button-prev),
.portfolio-main-swiper :deep(.swiper-button-next) {
    color: #0f766e;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}
.portfolio-main-swiper :deep(.swiper-button-prev::after),
.portfolio-main-swiper :deep(.swiper-button-next::after) {
    font-size: 0.75rem;
    font-weight: 800;
}
.portfolio-main-swiper :deep(.swiper-pagination-bullet-active) {
    background: #0f766e;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
