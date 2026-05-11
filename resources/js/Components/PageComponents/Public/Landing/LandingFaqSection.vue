<template>
    <section
        id="faq"
        class="scroll-mt-24 bg-slate-50 py-16 sm:py-20 lg:py-24"
        aria-labelledby="faq-heading"
        data-landing-section="faq"
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 id="faq-heading" class="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                    {{ block.title }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600 sm:text-xl">
                    {{ block.subtitle }}
                </p>
            </div>

            <div class="mt-12 space-y-4">
                <details
                    v-for="(item, index) in block.items"
                    :key="item.q"
                    class="group rounded-2xl bg-white p-2 shadow-md shadow-slate-200/60 ring-1 ring-slate-100 open:ring-primary-200"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-4 py-4 text-left font-display text-base font-bold text-slate-900 outline-none transition hover:bg-primary-50 focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:text-lg"
                    >
                        <span>{{ item.q }}</span>
                        <span class="text-primary-600 transition group-open:rotate-180" aria-hidden="true">
                            ▾
                        </span>
                    </summary>
                    <div class="border-t border-slate-100 px-4 pb-4 pt-3 text-sm leading-relaxed text-slate-600 sm:text-base">
                        <p :id="`faq-a-${index}`">
                            {{ item.a }}
                        </p>
                    </div>
                </details>
            </div>
        </div>
    </section>
</template>

<script setup>
defineProps({
    block: {
        type: Object,
        required: true,
    },
});
</script>
