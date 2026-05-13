<template>
    <section
        id="testimonials"
        class="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-50/90 py-12 sm:py-16 lg:py-24"
        aria-labelledby="testimonials-heading"
        data-landing-section="testimonials"
    >
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-secondary-300/50 to-transparent" />

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center sm:text-left lg:mx-0 lg:max-w-3xl">
                <h2
                    id="testimonials-heading"
                    class="font-display text-3xl font-black tracking-[0.03em] text-slate-900 sm:text-4xl lg:text-5xl lg:leading-[1.15]"
                >
                    {{ block.title }}
                </h2>
                <p class="mt-4 text-base leading-relaxed tracking-wide text-slate-600 sm:text-lg lg:text-xl lg:leading-relaxed">
                    {{ block.subtitle }}
                </p>
            </div>

            <div class="relative mt-10 sm:mt-12">
                <Swiper
                    class="testimonial-swiper !pb-16 pl-7 pr-7 sm:!pb-14 sm:pl-10 sm:pr-10 lg:pl-12 lg:pr-12"
                    :modules="modules"
                    :slides-per-view="1"
                    :space-between="16"
                    :loop="true"
                    :speed="1100"
                    :autoplay="{
                        delay: 16000,
                        disableOnInteraction: true,
                        pauseOnMouseEnter: true,
                    }"
                    :pagination="{ clickable: true, dynamicBullets: true }"
                    :navigation="true"
                    :breakpoints="{
                        480: { slidesPerView: 1, spaceBetween: 18 },
                        768: { slidesPerView: 1.12, spaceBetween: 22 },
                        1024: { slidesPerView: 2, spaceBetween: 26 },
                        1280: { slidesPerView: 2.35, spaceBetween: 28 },
                    }"
                    :grab-cursor="true"
                    :watch-overflow="false"
                    :touch-ratio="1"
                    :threshold="8"
                >
                    <SwiperSlide v-for="item in block.items" :key="item.name">
                        <figure
                            class="group relative flex h-full min-h-[260px] flex-col overflow-hidden rounded-[1.75rem] border border-slate-200/90 bg-white p-6 shadow-[0_22px_70px_-28px_rgba(15,23,42,0.18)] ring-1 ring-white transition duration-500 ease-out hover:-translate-y-1 hover:border-secondary-200/80 hover:shadow-[0_28px_80px_-26px_rgba(192,38,211,0.12)] sm:min-h-[280px] sm:rounded-3xl sm:p-8"
                        >
                            <div
                                class="absolute left-0 top-0 h-full w-1 rounded-l-[inherit] bg-gradient-to-b from-secondary-400 via-amber-400 to-primary-600 opacity-90"
                                aria-hidden="true"
                            />
                            <blockquote class="relative flex-1 pl-4 sm:pl-5">
                                <p
                                    class="text-[1.05rem] font-medium leading-relaxed tracking-wide text-slate-800 sm:text-lg sm:leading-relaxed lg:text-[1.125rem]"
                                >
                                    “{{ item.quote }}”
                                </p>
                            </blockquote>
                            <figcaption class="relative mt-8 flex items-center gap-4 border-t border-slate-100 pt-7">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 via-primary-700 to-secondary-500 text-sm font-bold text-white shadow-lg shadow-primary-900/15 ring-2 ring-white"
                                    aria-hidden="true"
                                >
                                    {{ initials(item.name) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold tracking-wide text-slate-900">
                                        {{ item.name }}
                                    </p>
                                    <p class="mt-0.5 text-sm leading-snug tracking-wide text-slate-500">
                                        {{ item.role }}
                                    </p>
                                </div>
                            </figcaption>
                        </figure>
                    </SwiperSlide>
                </Swiper>
            </div>

            <div
                class="mt-10 flex flex-wrap items-center justify-center gap-3 sm:mt-12 sm:gap-5"
                aria-label="Partner logos placeholder"
            >
                <span
                    v-for="logo in block.logos"
                    :key="logo"
                    class="rounded-full border border-slate-200 bg-white px-4 py-2 text-[0.7rem] font-bold uppercase tracking-wider text-slate-500 shadow-sm transition hover:border-secondary-200 hover:text-slate-700 sm:px-5 sm:text-xs"
                >
                    {{ logo }}
                </span>
            </div>
        </div>
    </section>
</template>

<script setup>
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import { Autoplay, Navigation, Pagination } from 'swiper/modules';
import { Swiper, SwiperSlide } from 'swiper/vue';

defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const modules = [Autoplay, Navigation, Pagination];

function initials(name) {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '');
    return letters.join('') || 'HS';
}
</script>

<style scoped>
.testimonial-swiper :deep(.swiper-button-prev),
.testimonial-swiper :deep(.swiper-button-next) {
    color: rgb(217 119 6);
    width: 1.85rem;
    height: 1.85rem;
    margin-top: 0;
    top: 50%;
    transform: translateY(-50%);
    transition:
        color 0.2s ease,
        transform 0.2s ease,
        opacity 0.2s ease;
}
.testimonial-swiper :deep(.swiper-button-prev) {
    left: 0;
}
.testimonial-swiper :deep(.swiper-button-next) {
    right: 0;
}
.testimonial-swiper :deep(.swiper-button-prev:hover),
.testimonial-swiper :deep(.swiper-button-next:hover) {
    color: rgb(245 158 11);
    transform: translateY(-50%) scale(1.05);
}
.testimonial-swiper :deep(.swiper-button-prev::after),
.testimonial-swiper :deep(.swiper-button-next::after) {
    font-size: 0.72rem;
    font-weight: 800;
}
.testimonial-swiper :deep(.swiper-pagination) {
    bottom: 0.25rem !important;
}
.testimonial-swiper :deep(.swiper-pagination-bullet) {
    background: rgb(148 163 184);
    opacity: 0.45;
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}
.testimonial-swiper :deep(.swiper-pagination-bullet-active) {
    background: rgb(245 158 11);
    opacity: 1;
    transform: scale(1.15);
}
</style>
