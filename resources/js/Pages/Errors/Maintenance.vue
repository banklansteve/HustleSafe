<template>
    <Head title="Maintenance | HustleSafe" />

    <div class="hs-maintenance">
        <div class="grid-bg" aria-hidden="true"></div>

        <div class="star s-1" aria-hidden="true"></div>
        <div class="star s-2" aria-hidden="true"></div>
        <div class="star s-3" aria-hidden="true"></div>
        <div class="star s-4" aria-hidden="true"></div>
        <div class="star s-5" aria-hidden="true"></div>
        <div class="star s-6" aria-hidden="true"></div>

        <main class="container" role="main">
            <div class="illustration" aria-hidden="true">
                <div class="particle pa-1"></div>
                <div class="particle pa-2"></div>
                <div class="particle pa-3"></div>

                <svg class="gear gear-big" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 3L22.8 8.2L28.6 7.4L29.4 13.2L34.6 16L31.8 21.2L34.6 26.4L29.4 29.2L28.6 35L22.8 34.2L20 39.4L17.2 34.2L11.4 35L10.6 29.2L5.4 26.4L8.2 21.2L5.4 16L10.6 13.2L11.4 7.4L17.2 8.2Z" stroke="#0d9488" stroke-width="1.8" stroke-linejoin="round" />
                    <circle cx="20" cy="21" r="6" stroke="#0d9488" stroke-width="1.8" />
                </svg>

                <svg class="gear gear-sm" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 2L15.8 5.8L20 5.2L20.6 9.4L24.4 11.2L22.6 15L24.4 18.8L20.6 20.6L20 24.8L15.8 24.2L14 28L12.2 24.2L8 24.8L7.4 20.6L3.6 18.8L5.4 15L3.6 11.2L7.4 9.4L8 5.2L12.2 5.8Z" stroke="#14b8a6" stroke-width="1.5" stroke-linejoin="round" />
                    <circle cx="14" cy="15" r="4" stroke="#14b8a6" stroke-width="1.5" />
                </svg>

                <div class="monitor">
                    <div class="mon-screen">
                        <div class="mon-label">UPDATING...</div>
                        <div class="mon-bars">
                            <div class="mbar"></div>
                            <div class="mbar"></div>
                            <div class="mbar"></div>
                        </div>
                        <div class="mon-track">
                            <div class="mon-fill"></div>
                        </div>
                    </div>
                    <div class="mon-stand"></div>
                    <div class="mon-base"></div>
                </div>

                <div class="worker">
                    <div class="wk-head"></div>
                    <div class="wk-body">
                        <div class="wk-arm">
                            <span class="wrench-icon" aria-hidden="true">🔧</span>
                        </div>
                    </div>
                </div>

                <div class="bench-floor"></div>
            </div>

            <div class="brand-lockup">
                <svg class="brand-mark" viewBox="0 0 72 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="9" y="6" width="12" height="68" rx="6" fill="#0d9488" />
                    <rect x="51" y="6" width="12" height="68" rx="6" fill="#14b8a6" />
                    <path d="M 21 43 Q 36 23 51 43 L 51 37 Q 36 17 21 37 Z" fill="#5eead4" />
                    <circle cx="21" cy="40" r="7.5" fill="#0d9488" />
                    <circle cx="21" cy="40" r="3.5" fill="#ccfbf1" />
                    <circle cx="51" cy="40" r="7.5" fill="#14b8a6" />
                    <circle cx="51" cy="40" r="3.5" fill="#f0fdfa" />
                </svg>
                <span class="brand-name">Hustle<span class="brand-accent">Safe</span></span>
            </div>

            <h1 class="headline">
                We're <span class="accent">Levelling Up</span>
            </h1>

            <p class="body-text">
                {{ displayMessage }}
            </p>

            <div class="status-row" role="list" aria-label="System status">
                <div class="pill pill-done" role="listitem">
                    <span class="pill-dot" aria-hidden="true"></span>
                    Database
                </div>
                <div class="pill pill-done" role="listitem">
                    <span class="pill-dot" aria-hidden="true"></span>
                    Payments
                </div>
                <div class="pill pill-active" role="listitem">
                    <span class="pill-dot" aria-hidden="true"></span>
                    Core Services
                </div>
                <div class="pill pill-wait" role="listitem">
                    <span class="pill-dot" aria-hidden="true"></span>
                    Final Tests
                </div>
            </div>

            <div class="countdown" aria-label="Time remaining" role="timer">
                <div class="cd-block">
                    <div class="cd-num" aria-label="Hours">{{ hours }}</div>
                    <div class="cd-label">Hours</div>
                </div>
                <div class="cd-sep" aria-hidden="true">:</div>
                <div class="cd-block">
                    <div class="cd-num" aria-label="Minutes">{{ minutes }}</div>
                    <div class="cd-label">Mins</div>
                </div>
                <div class="cd-sep" aria-hidden="true">:</div>
                <div class="cd-block">
                    <div class="cd-num" aria-label="Seconds">{{ seconds }}</div>
                    <div class="cd-label">Secs</div>
                </div>
            </div>

            <div class="contact-bar">
                Follow updates on
                <a href="https://twitter.com/hustlesafe" target="_blank" rel="noopener noreferrer">@HustleSafe</a>
                &nbsp;·&nbsp;
                Questions?
                <a :href="`mailto:${supportEmail}`">{{ supportEmail }}</a>
            </div>
        </main>

        <div v-if="isSuperAdmin" class="admin-rescue">
            <p class="admin-rescue-text">Super admin — turn maintenance off</p>
            <div class="admin-rescue-actions">
                <button
                    type="button"
                    class="admin-rescue-btn"
                    :disabled="turningOff"
                    @click="turnOff"
                >
                    {{ turningOff ? 'Turning off…' : 'Turn maintenance OFF' }}
                </button>
                <a
                    :href="`${route('admin.settings.index')}?section=maintenance`"
                    class="admin-rescue-link"
                >
                    Settings
                </a>
            </div>
            <p v-if="toast" class="admin-rescue-toast" :class="toastError ? 'is-error' : 'is-ok'">{{ toast }}</p>
        </div>
    </div>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    message: { type: String, default: null },
    returnTime: { type: String, default: null },
});

const page = usePage();
const turningOff = ref(false);
const toast = ref('');
const toastError = ref(false);

const supportEmail = 'support@hustlesafe.ng';
const defaultMessage = 'HustleSafe is getting some upgrades. Our engineers are working hard so your hustle stays safe. We\'ll be back shortly.';

const displayMessage = computed(() => props.message?.trim() || defaultMessage);
const isSuperAdmin = computed(() => page.props.auth?.user?.role?.slug === 'super_admin');

const targetTime = resolveTargetTime(props.returnTime);
const remaining = ref(Math.max(0, Math.floor((targetTime - Date.now()) / 1000)));

let timer = null;

const hours = computed(() => String(Math.floor(remaining.value / 3600)).padStart(2, '0'));
const minutes = computed(() => String(Math.floor((remaining.value % 3600) / 60)).padStart(2, '0'));
const seconds = computed(() => String(remaining.value % 60).padStart(2, '0'));

onMounted(() => {
    timer = setInterval(() => {
        remaining.value = Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
    }, 1000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

function resolveTargetTime(returnTime) {
    if (!returnTime?.trim()) {
        return Date.now() + 2.5 * 60 * 60 * 1000;
    }

    const raw = returnTime.trim();
    const parsed = Date.parse(raw.includes('T') ? raw : raw.replace(' ', 'T'));

    if (!Number.isNaN(parsed) && parsed > Date.now()) {
        return parsed;
    }

    return Date.now() + 2.5 * 60 * 60 * 1000;
}

async function turnOff() {
    turningOff.value = true;
    toast.value = '';
    toastError.value = false;
    try {
        const { data } = await window.axios.post(
            route('admin.api.maintenance.off'),
            {},
            { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
        );
        toast.value = data.message || 'Maintenance is off.';
        window.location.replace(route('admin.dashboard'));
    } catch (err) {
        toastError.value = true;
        toast.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {})?.flat()?.[0]
            || 'Could not turn maintenance off.';
    } finally {
        turningOff.value = false;
    }
}
</script>

<style scoped>
.hs-maintenance {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    min-height: 100vh;
    min-height: 100dvh;
    width: 100%;
    background: #0b1f1f;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 48px 20px 120px;
    box-sizing: border-box;
}

.grid-bg {
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(rgba(13, 148, 136, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(13, 148, 136, 0.07) 1px, transparent 1px);
    background-size: 44px 44px;
    pointer-events: none;
}

.star {
    position: fixed;
    background: #5eead4;
    border-radius: 50%;
    pointer-events: none;
    animation: twinkle 2.5s ease-in-out infinite;
}

.s-1 { width: 3px; height: 3px; top: 7%; left: 10%; animation-delay: 0s; }
.s-2 { width: 2px; height: 2px; top: 14%; right: 16%; animation-delay: 0.6s; }
.s-3 { width: 4px; height: 4px; top: 4%; left: 52%; animation-delay: 1.1s; }
.s-4 { width: 2px; height: 2px; bottom: 18%; left: 7%; animation-delay: 1.7s; }
.s-5 { width: 3px; height: 3px; bottom: 13%; right: 10%; animation-delay: 0.9s; }
.s-6 { width: 2px; height: 2px; top: 30%; right: 6%; animation-delay: 0.3s; }

@keyframes twinkle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.15; transform: scale(0.4); }
}

.container {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    max-width: 520px;
    width: 100%;
}

.illustration {
    position: relative;
    width: 280px;
    height: 210px;
    margin-bottom: 32px;
    flex-shrink: 0;
}

.particle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: #5eead4;
    border-radius: 50%;
    opacity: 0;
    pointer-events: none;
}

.pa-1 { top: 28%; left: 22%; animation: particleFly 3.2s 0s ease-in-out infinite; }
.pa-2 { top: 18%; right: 28%; animation: particleFly 3.8s 0.9s ease-in-out infinite; }
.pa-3 { top: 48%; left: 14%; animation: particleFly 2.9s 1.7s ease-in-out infinite; }

@keyframes particleFly {
    0% { opacity: 0; transform: translateY(0) scale(0); }
    25% { opacity: 1; transform: translateY(-16px) scale(1); }
    100% { opacity: 0; transform: translateY(-48px) scale(0.4); }
}

.gear {
    position: absolute;
}

.gear-big {
    top: -8px;
    right: 28px;
    width: 40px;
    height: 40px;
    animation: spinGear 5s linear infinite;
}

.gear-sm {
    top: 18px;
    right: 54px;
    width: 26px;
    height: 26px;
    animation: spinGear 4s linear infinite reverse;
}

@keyframes spinGear {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.monitor {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    bottom: 42px;
}

.mon-screen {
    width: 118px;
    height: 76px;
    background: #0f172a;
    border: 2.5px solid #0d9488;
    border-radius: 8px 8px 0 0;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
}

.mon-label {
    position: absolute;
    top: 9px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 8px;
    font-weight: 800;
    color: #5eead4;
    letter-spacing: 1.5px;
    animation: labelBlink 1s step-end infinite;
}

@keyframes labelBlink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.25; }
}

.mon-bars {
    position: absolute;
    top: 22px;
    left: 10px;
    right: 10px;
}

.mbar {
    height: 3px;
    background: #0d9488;
    border-radius: 2px;
    margin-bottom: 4px;
    opacity: 0.5;
    animation: scanBar 2.2s ease-in-out infinite;
}

.mbar:nth-child(1) { width: 100%; animation-delay: 0s; }
.mbar:nth-child(2) { width: 78%; animation-delay: 0.35s; }
.mbar:nth-child(3) { width: 58%; animation-delay: 0.7s; }

@keyframes scanBar {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.9; }
}

.mon-track {
    position: absolute;
    bottom: 12px;
    left: 10px;
    right: 10px;
    height: 5px;
    background: #1e293b;
    border-radius: 3px;
    overflow: hidden;
}

.mon-fill {
    height: 100%;
    background: #14b8a6;
    border-radius: 3px;
    animation: loadBar 3s ease-in-out infinite;
}

@keyframes loadBar {
    0% { width: 0%; }
    65% { width: 100%; }
    100% { width: 100%; }
}

.mon-stand {
    width: 9px;
    height: 20px;
    background: #1e3a5f;
    margin: 0 auto;
    border-radius: 3px;
}

.mon-base {
    width: 44px;
    height: 7px;
    background: #1e3a5f;
    border-radius: 4px;
    margin: 0 auto;
}

.worker {
    position: absolute;
    right: 18px;
    bottom: 40px;
}

.wk-head {
    width: 22px;
    height: 22px;
    background: #f59e0b;
    border-radius: 50%;
    margin: 0 auto 3px;
}

.wk-body {
    width: 28px;
    height: 32px;
    background: #0d9488;
    border-radius: 8px 8px 0 0;
    margin: 0 auto;
    position: relative;
}

.wk-arm {
    position: absolute;
    top: 5px;
    left: -16px;
    width: 20px;
    height: 6px;
    background: #f59e0b;
    border-radius: 3px;
    transform-origin: right center;
    animation: wkSwing 1.1s ease-in-out infinite alternate;
}

.wrench-icon {
    position: absolute;
    top: -4px;
    left: -16px;
    font-size: 16px;
    transform-origin: right bottom;
}

@keyframes wkSwing {
    0% { transform: rotate(-28deg); }
    100% { transform: rotate(12deg); }
}

.bench-floor {
    position: absolute;
    bottom: 32px;
    left: 0;
    right: 0;
    height: 3px;
    background: #1e3a5f;
    border-radius: 2px;
}

.brand-lockup {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
}

.brand-mark {
    width: 32px;
    height: 36px;
}

.brand-name {
    font-size: 22px;
    font-weight: 900;
    color: #f0fdfa;
    letter-spacing: -0.8px;
}

.brand-accent {
    color: #14b8a6;
    font-weight: 300;
}

.headline {
    font-size: clamp(32px, 8vw, 48px);
    font-weight: 900;
    color: #f0fdfa;
    line-height: 1.1;
    letter-spacing: -2px;
    margin: 0 0 14px;
}

.accent {
    color: #14b8a6;
}

.body-text {
    font-size: 15px;
    color: #94a3b8;
    line-height: 1.75;
    max-width: 380px;
    margin: 0 0 28px;
}

.status-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 30px;
}

.pill {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 700;
    padding: 7px 15px;
    border-radius: 99px;
    letter-spacing: 0.2px;
}

.pill-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.pill-done {
    background: rgba(13, 148, 136, 0.14);
    border: 1px solid rgba(13, 148, 136, 0.28);
    color: #5eead4;
}

.pill-done .pill-dot {
    background: #5eead4;
}

.pill-active {
    background: rgba(20, 184, 166, 0.14);
    border: 1px solid rgba(20, 184, 166, 0.3);
    color: #14b8a6;
}

.pill-active .pill-dot {
    background: #14b8a6;
    animation: pulseDot 1.4s ease-in-out infinite;
}

.pill-wait {
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.25);
    color: #fbbf24;
}

.pill-wait .pill-dot {
    background: #f59e0b;
}

@keyframes pulseDot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.7); opacity: 0.4; }
}

.countdown {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
}

.cd-block {
    text-align: center;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(13, 148, 136, 0.22);
    border-radius: 12px;
    padding: 14px 20px;
    min-width: 68px;
}

.cd-num {
    font-size: 30px;
    font-weight: 900;
    color: #14b8a6;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -1px;
}

.cd-label {
    font-size: 9px;
    color: #475569;
    letter-spacing: 2px;
    font-weight: 700;
    margin-top: 5px;
    text-transform: uppercase;
}

.cd-sep {
    font-size: 26px;
    font-weight: 900;
    color: #0d9488;
    line-height: 1;
    margin-bottom: 18px;
    animation: sepBlink 1s step-end infinite;
}

@keyframes sepBlink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.2; }
}

.contact-bar {
    background: rgba(13, 148, 136, 0.09);
    border: 1px solid rgba(13, 148, 136, 0.2);
    border-radius: 12px;
    padding: 14px 22px;
    font-size: 13px;
    color: #94a3b8;
    max-width: 380px;
    line-height: 1.6;
}

.contact-bar a {
    color: #14b8a6;
    text-decoration: none;
    font-weight: 700;
    transition: color 0.15s;
}

.contact-bar a:hover {
    color: #5eead4;
}

.admin-rescue {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    z-index: 30;
    border-top: 1px solid rgba(13, 148, 136, 0.35);
    background: rgba(11, 31, 31, 0.95);
    backdrop-filter: blur(12px);
    padding: 14px 20px;
    text-align: center;
}

.admin-rescue-text {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 700;
    color: #5eead4;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.admin-rescue-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.admin-rescue-btn {
    border-radius: 12px;
    background: #14b8a6;
    padding: 10px 18px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #042f2e;
    transition: background 0.15s;
}

.admin-rescue-btn:hover:not(:disabled) {
    background: #5eead4;
}

.admin-rescue-btn:disabled {
    opacity: 0.6;
}

.admin-rescue-link {
    border-radius: 12px;
    border: 1px solid rgba(94, 234, 212, 0.35);
    padding: 10px 18px;
    font-size: 12px;
    font-weight: 800;
    color: #5eead4;
    text-decoration: none;
}

.admin-rescue-link:hover {
    background: rgba(13, 148, 136, 0.15);
}

.admin-rescue-toast {
    margin: 10px 0 0;
    font-size: 12px;
    font-weight: 700;
}

.admin-rescue-toast.is-ok {
    color: #5eead4;
}

.admin-rescue-toast.is-error {
    color: #fca5a5;
}

@media (max-width: 480px) {
    .illustration {
        transform: scale(0.85);
    }

    .countdown {
        gap: 8px;
    }

    .cd-block {
        padding: 11px 14px;
        min-width: 56px;
    }

    .cd-num {
        font-size: 24px;
    }

    .cd-sep {
        font-size: 20px;
    }

    .status-row {
        gap: 7px;
    }

    .pill {
        font-size: 11px;
        padding: 6px 11px;
    }
}
</style>
