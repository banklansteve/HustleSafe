<template>
    <section
        class="relative overflow-hidden bg-white py-16 sm:py-20 lg:py-24"
        aria-labelledby="testimonials-heading"
        data-landing-section="testimonials"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <h2 id="testimonials-heading" class="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                    {{ block.title }}
                </h2>
                <p class="mt-4 text-lg text-slate-600 sm:text-xl">
                    {{ block.subtitle }}
                </p>
            </div>

            <div class="relative mt-12">
                <Swiper
                    class="testimonial-swiper !pb-14 transition-opacity duration-500"
                    :modules="modules"
                    :slides-per-view="1"
                    :space-between="20"
                    :loop="true"
                    :speed="680"
                    :autoplay="{
                        delay: 5200,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    }"
                    :pagination="{ clickable: true, dynamicBullets: true }"
                    :navigation="true"
                    :breakpoints="{
                        640: { slidesPerView: 1.08, spaceBetween: 22 },
                        1024: { slidesPerView: 2, spaceBetween: 24 },
                        1280: { slidesPerView: 2.25, spaceBetween: 28 },
                    }"
                    :grab-cursor="true"
                    :watch-overflow="false"
                >
                    <SwiperSlide v-for="item in block.items" :key="item.name">
                        <figure
                            class="flex h-full min-h-[280px] flex-col rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-100 transition duration-500 ease-out hover:-translate-y-1 hover:shadow-lg hover:ring-primary-100 sm:p-8"
                        >
                            <blockquote class="flex-1">
                                <p class="text-base leading-relaxed tracking-wide text-slate-700">
                                    “{{ item.quote }}”
                                </p>
                            </blockquote>
                            <figcaption class="mt-6 flex items-center gap-3 border-t border-slate-200 pt-6">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-primary-800 text-sm font-bold text-white shadow-inner ring-1 ring-secondary-300/30"
                                    aria-hidden="true"
                                >
                                    {{ initials(item.name) }}
                                </div>
                                <div>
                                    <p class="font-semibold tracking-wide text-slate-900">
                                        {{ item.name }}
                                    </p>
                                    <p class="text-sm leading-snug text-slate-600">
                                        {{ item.role }}
                                    </p>
                                </div>
                            </figcaption>
                        </figure>
                    </SwiperSlide>
                </Swiper>
            </div>

            <div class="mt-12 flex flex-wrap items-center justify-center gap-4 sm:gap-8" aria-label="Partner logos placeholder">
                <span
                    v-for="logo in block.logos"
                    :key="logo"
                    class="rounded-full bg-slate-100 px-5 py-2 text-xs font-bold uppercase tracking-wider text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50"
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
    color: rgb(13 148 136);
    transition:
        color 0.2s ease,
        transform 0.2s ease;
}
.testimonial-swiper :deep(.swiper-button-prev:hover),
.testimonial-swiper :deep(.swiper-button-next:hover) {
    color: rgb(15 118 110);
    transform: scale(1.06);
}
.testimonial-swiper :deep(.swiper-button-prev::after),
.testimonial-swiper :deep(.swiper-button-next::after) {
    font-size: 1.35rem;
    font-weight: 700;
}
.testimonial-swiper :deep(.swiper-pagination-bullet) {
    background: rgb(148 163 184);
    opacity: 0.55;
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
}
.testimonial-swiper :deep(.swiper-pagination-bullet-active) {
    background: rgb(13 148 136);
    opacity: 1;
    transform: scale(1.12);
}
</style>
