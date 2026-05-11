<template>
    <div>
        <Head>
            <title>{{ seo.title }}</title>
            <meta name="description" :content="seo.description" />
            <meta name="keywords" :content="seo.keywords" />
            <meta name="robots" content="index,follow" />
            <link rel="canonical" :href="seo.canonical" />

            <meta property="og:type" content="website" />
            <meta property="og:url" :content="seo.canonical" />
            <meta property="og:title" :content="seo.og_title" />
            <meta property="og:description" :content="seo.og_description" />
            <meta property="og:image" :content="seo.og_image" />

            <meta name="twitter:card" :content="seo.twitter_card" />
            <meta name="twitter:title" :content="seo.og_title" />
            <meta name="twitter:description" :content="seo.og_description" />
            <meta name="twitter:image" :content="seo.og_image" />

            <component :is="'script'" type="application/ld+json" v-text="structuredDataJson" />
        </Head>

        <MarketingLayout>
            <LandingNavbar
                :nav="copy.nav"
                :can-login="canLogin"
                :can-register="canRegister"
            />

            <main id="main-content" tabindex="-1">
                <LandingHeroSection :hero="copy.hero" :can-register="canRegister" />
                <LandingHowItWorksSection :block="copy.how_it_works" />
                <LandingTrustSection :block="copy.trust" />
                <LandingCategoriesSection
                    :block="copy.categories"
                    :can-register="canRegister"
                />
                <LandingPopularJobsSection
                    :block="copy.popular_jobs"
                    :can-register="canRegister"
                />
                <LandingTestimonialsSection :block="copy.testimonials" />
                <LandingFaqSection :block="copy.faq" />
                <LandingFooter :block="copy.footer" />
            </main>
        </MarketingLayout>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import LandingCategoriesSection from '@/Components/PageComponents/Public/Landing/LandingCategoriesSection.vue';
import LandingFaqSection from '@/Components/PageComponents/Public/Landing/LandingFaqSection.vue';
import LandingFooter from '@/Components/PageComponents/Public/Landing/LandingFooter.vue';
import LandingHeroSection from '@/Components/PageComponents/Public/Landing/LandingHeroSection.vue';
import LandingHowItWorksSection from '@/Components/PageComponents/Public/Landing/LandingHowItWorksSection.vue';
import LandingNavbar from '@/Components/PageComponents/Public/Landing/LandingNavbar.vue';
import LandingPopularJobsSection from '@/Components/PageComponents/Public/Landing/LandingPopularJobsSection.vue';
import LandingTestimonialsSection from '@/Components/PageComponents/Public/Landing/LandingTestimonialsSection.vue';
import LandingTrustSection from '@/Components/PageComponents/Public/Landing/LandingTrustSection.vue';
import MarketingLayout from '@/Layouts/Public/MarketingLayout.vue';

const props = defineProps({
    seo: {
        type: Object,
        required: true,
    },
    structuredData: {
        type: Object,
        required: true,
    },
    copy: {
        type: Object,
        required: true,
    },
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const structuredDataJson = computed(() => JSON.stringify(props.structuredData));
</script>
