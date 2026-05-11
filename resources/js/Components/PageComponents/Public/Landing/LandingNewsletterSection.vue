<template>
    <section
        id="newsletter"
        class="relative scroll-mt-24 overflow-hidden bg-gradient-to-b from-white via-slate-50/90 to-white py-12 sm:py-16 lg:py-[4.5rem]"
        aria-labelledby="newsletter-heading"
        data-landing-section="newsletter"
    >
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-secondary-300/60 to-transparent" />

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-900 via-primary-900 to-slate-950 px-6 py-10 shadow-[0_28px_80px_-32px_rgba(15,118,110,0.45)] ring-1 ring-white/10 sm:px-10 sm:py-12 lg:px-14 lg:py-14"
            >
                <div
                    class="pointer-events-none absolute -right-20 top-0 h-72 w-72 rounded-full bg-secondary-500/25 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute -left-16 bottom-0 h-56 w-56 rounded-full bg-primary-400/20 blur-3xl"
                    aria-hidden="true"
                />

                <div class="relative mx-auto max-w-3xl text-center lg:mx-0 lg:max-w-none lg:text-left">
                    <div class="lg:flex lg:items-end lg:justify-between lg:gap-12">
                        <div class="lg:max-w-xl">
                            <p
                                class="text-xs font-bold uppercase tracking-[0.28em] text-secondary-300"
                            >
                                {{ block.kicker }}
                            </p>
                            <h2
                                id="newsletter-heading"
                                class="font-display mt-3 text-2xl font-black tracking-[0.03em] text-white sm:text-3xl lg:text-[2rem] lg:leading-tight"
                            >
                                {{ block.title }}
                            </h2>
                            <p class="mt-3 text-sm leading-relaxed tracking-wide text-teal-100/95 sm:text-base lg:mt-4 lg:text-[1.05rem] lg:leading-relaxed">
                                {{ block.subtitle }}
                            </p>
                        </div>

                        <form
                            class="mt-8 flex w-full flex-col gap-3 sm:mx-auto sm:max-w-lg sm:flex-row sm:items-stretch lg:mt-0 lg:mx-0 lg:max-w-md lg:shrink-0 lg:flex-col xl:max-w-lg xl:flex-row xl:items-center"
                            @submit.prevent="submit"
                        >
                            <label class="sr-only" for="newsletter-email">{{ block.placeholder }}</label>
                            <input
                                id="newsletter-email"
                                v-model="form.email"
                                type="email"
                                name="email"
                                autocomplete="email"
                                required
                                :placeholder="block.placeholder"
                                class="min-h-[3rem] w-full rounded-2xl border border-white/15 bg-white/10 px-5 py-3.5 text-[0.9375rem] font-medium tracking-wide text-white shadow-inner shadow-black/20 backdrop-blur-md placeholder:text-teal-100/55 focus:border-secondary-400/80 focus:outline-none focus:ring-4 focus:ring-secondary-500/25 sm:min-h-[3.25rem] sm:flex-1 lg:text-base"
                            />
                            <button
                                type="submit"
                                class="inline-flex min-h-[3rem] shrink-0 items-center justify-center rounded-2xl bg-gradient-to-r from-secondary-500 to-secondary-600 px-8 py-3.5 text-sm font-extrabold uppercase tracking-[0.12em] text-slate-900 shadow-lg shadow-secondary-900/30 transition duration-300 ease-out hover:from-secondary-400 hover:to-secondary-500 focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-300/50 disabled:cursor-not-allowed disabled:opacity-60 sm:min-h-[3.25rem] sm:px-10"
                                :disabled="form.processing"
                            >
                                {{ block.button }}
                            </button>
                        </form>
                    </div>

                    <p
                        v-if="flashNewsletter"
                        class="mt-6 text-center text-sm font-semibold tracking-wide text-secondary-200 lg:text-left"
                        role="status"
                    >
                        {{ flashNewsletter }}
                    </p>
                    <p
                        v-else
                        class="mt-6 text-center text-xs tracking-wide text-teal-200/80 lg:text-left sm:text-sm"
                    >
                        {{ block.hint }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const flashNewsletter = computed(() => page.props.flash?.newsletter ?? null);

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}
</script>
