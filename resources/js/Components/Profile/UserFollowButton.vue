<template>
    <div class="relative inline-flex flex-col items-center gap-1.5">
        <div class="relative">
            <PortfolioHeartBurst :trigger="burstKey" />
            <button
                type="button"
                class="relative inline-flex min-h-12 items-center gap-2 rounded-2xl border px-5 py-2.5 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 disabled:cursor-not-allowed disabled:opacity-50"
                :class="
                    following
                        ? 'border-rose-200 bg-gradient-to-br from-rose-50 to-white text-rose-700 shadow-md shadow-rose-200/40'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:text-rose-600'
                "
                :disabled="busy"
                :aria-pressed="following"
                :aria-label="following ? 'Unfollow' : 'Follow'"
                @click="onClick"
            >
                <ReLoader4Line v-if="busy" class="h-5 w-5 animate-spin text-rose-500" />
                <UserPlusIcon v-else-if="!following" class="h-5 w-5 text-rose-500" stroke-width="2" />
                <HeartIcon v-else class="h-5 w-5 fill-rose-500 text-rose-500" stroke-width="2" />
                <span>{{ following ? 'Following' : 'Follow' }}</span>
            </button>
        </div>
        <div class="max-w-[14rem] text-center text-[10px] font-bold uppercase leading-tight tracking-wide text-teal-100/90">
            <p>{{ followersDetail }} followers</p>
            <p class="mt-1 text-teal-100/80">{{ followingDetail }} following</p>
        </div>
    </div>
</template>

<script setup>
import PortfolioHeartBurst from '@/Components/Portfolio/PortfolioHeartBurst.vue';
import { xsrfToken } from '@/utils/csrfHeader';
import { formatCompactCountWithFull } from '@/utils/formatCompactCount';
import { HeartIcon, UserPlusIcon } from '@heroicons/vue/24/outline';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    userSlug: {
        type: String,
        required: true,
    },
    initialFollowing: {
        type: Boolean,
        default: false,
    },
    initialFollowersCount: {
        type: Number,
        default: 0,
    },
    initialFollowingCount: {
        type: Number,
        default: 0,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    viewerCanFollow: {
        type: Boolean,
        default: false,
    },
});

const following = ref(props.initialFollowing);
const followersCount = ref(Number(props.initialFollowersCount) || 0);
const followingCount = ref(Number(props.initialFollowingCount) || 0);
const busy = ref(false);
const burstKey = ref(0);

watch(
    () => props.initialFollowing,
    (v) => {
        following.value = v;
    },
);
watch(
    () => props.initialFollowersCount,
    (v) => {
        followersCount.value = Number(v) || 0;
    },
);
watch(
    () => props.initialFollowingCount,
    (v) => {
        followingCount.value = Number(v) || 0;
    },
);

const followersDetail = computed(() => formatCompactCountWithFull(followersCount.value));
const followingDetail = computed(() => formatCompactCountWithFull(followingCount.value));

async function onClick() {
    if (busy.value || !props.viewerCanFollow) {
        return;
    }
    if (!props.isAuthenticated) {
        router.visit(route('login'));

        return;
    }
    busy.value = true;
    try {
        const { data } = await axios.post(
            route('users.follow.toggle', { slug: props.userSlug }),
            {},
            {
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            },
        );
        const was = following.value;
        following.value = !!data.following;
        followersCount.value = Number(data.followers_count) || 0;
        followingCount.value = Number(data.following_count) || followingCount.value;
        if (!was && following.value) {
            burstKey.value += 1;
        }
    } catch {
        // optional toast
    } finally {
        busy.value = false;
    }
}
</script>
