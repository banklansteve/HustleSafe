<template>
    <div class="relative inline-flex flex-col items-center gap-1">
        <div class="relative">
            <PortfolioHeartBurst :trigger="burstKey" />
            <button
                type="button"
                class="relative inline-flex h-12 w-12 items-center justify-center rounded-full border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 disabled:cursor-not-allowed disabled:opacity-50"
                :class="
                    favorited
                        ? 'border-rose-200 bg-gradient-to-br from-rose-50 to-white text-rose-600 shadow-md shadow-rose-200/50'
                        : 'border-slate-200 bg-white text-slate-500 hover:border-rose-200 hover:text-rose-500'
                "
                :disabled="disabled || busy"
                :aria-pressed="favorited"
                :aria-label="favorited ? 'Unlike this portfolio' : 'Like this portfolio'"
                @click="onClick"
            >
                <ReLoader4Line v-if="busy" class="h-6 w-6 animate-spin text-rose-500" />
                <HeartIcon
                    v-else
                    class="h-6 w-6 transition-transform duration-200"
                    :class="favorited ? 'scale-110 fill-rose-500 text-rose-500' : ''"
                    stroke-width="1.75"
                />
            </button>
        </div>
        <p class="text-center text-[11px] font-bold uppercase tracking-wide text-slate-500">
            {{ countLabel }} likes
        </p>
    </div>
</template>

<script setup>
import { xsrfToken } from '@/utils/csrfHeader';
import { formatCompactCount } from '@/utils/formatCompactCount';
import { HeartIcon } from '@heroicons/vue/24/outline';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import PortfolioHeartBurst from './PortfolioHeartBurst.vue';

const props = defineProps({
    portfolioSlug: {
        type: String,
        required: true,
    },
    initialFavorited: {
        type: Boolean,
        default: false,
    },
    initialCount: {
        type: Number,
        default: 0,
    },
    /** Owner or guest — no favourite action */
    disabled: {
        type: Boolean,
        default: false,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
});

const favorited = ref(props.initialFavorited);
const count = ref(Number(props.initialCount) || 0);
const busy = ref(false);
const burstKey = ref(0);

watch(
    () => props.initialFavorited,
    (v) => {
        favorited.value = v;
    },
);
watch(
    () => props.initialCount,
    (v) => {
        count.value = Number(v) || 0;
    },
);

const countLabel = computed(() => formatCompactCount(count.value));

async function onClick() {
    if (props.disabled || busy.value) {
        return;
    }
    if (!props.isAuthenticated) {
        router.visit(route('login'));

        return;
    }
    busy.value = true;
    try {
        const { data } = await axios.post(
            route('portfolio.favorite', { portfolio: props.portfolioSlug }),
            {},
            {
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            },
        );
        const was = favorited.value;
        favorited.value = !!data.favorited;
        count.value = Number(data.favorites_count) || 0;
        if (!was && favorited.value) {
            burstKey.value += 1;
        }
    } catch {
        // silent — optional toast
    } finally {
        busy.value = false;
    }
}
</script>
