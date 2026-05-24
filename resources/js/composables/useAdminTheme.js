import { computed, inject, provide, ref, watch } from 'vue';

const STORAGE_KEY = 'hustlesafe-admin-theme';
const INJECT_KEY = 'adminTheme';

/** Dark: PowerPixel-style — dark sidebar + dark main + elevated slate cards */
const darkChrome = {
    root: 'bg-slate-950 text-slate-100',
    aside: 'border-slate-800 bg-slate-950 text-white',
    header: 'border-slate-800 bg-slate-950 text-white',
    main: 'bg-slate-900 text-slate-100',
    brandMark: 'bg-primary-500 text-slate-950',
    brandEyebrow: 'text-slate-500',
    brandTitle: 'text-white',
    canvasTitle: 'text-white',
    canvasMuted: 'text-slate-400',
    canvasLabel: 'text-slate-500',
    navActive: 'bg-primary-600 text-white shadow-lg shadow-black/30',
    navIdle: 'text-slate-400 hover:bg-slate-800 hover:text-white',
    navIconActive: 'text-white',
    navIconIdle: 'text-slate-400 group-hover:text-white',
    toggleBtn: 'border-slate-700 bg-slate-800 text-white hover:bg-slate-700',
    btnPrimary: 'bg-primary-500 text-slate-950 hover:bg-primary-400 shadow-lg shadow-black/25',
    btnGhost: 'border-slate-600 bg-slate-800/80 text-slate-200 hover:bg-slate-700 hover:text-white',
    flash: 'border-emerald-500/40 bg-emerald-950/50 text-emerald-100',
    link: 'text-primary-400 hover:text-primary-300 underline decoration-primary-500/50',
    overlay: 'bg-black/60',
};

/** Light: Matrix-style — white sidebar + light gray main + white cards */
const lightChrome = {
    root: 'bg-slate-100 text-slate-900',
    aside: 'border-slate-200 bg-white text-slate-900',
    header: 'border-slate-200 bg-white text-slate-900',
    main: 'bg-slate-100 text-slate-900',
    brandMark: 'bg-primary-600 text-white',
    brandEyebrow: 'text-primary-700',
    brandTitle: 'text-slate-900',
    canvasTitle: 'text-slate-900',
    canvasMuted: 'text-slate-600',
    canvasLabel: 'text-slate-500',
    navActive: 'bg-primary-600 text-white shadow-md shadow-primary-900/15',
    navIdle: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    navIconActive: 'text-white',
    navIconIdle: 'text-slate-500 group-hover:text-primary-700',
    toggleBtn: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
    btnPrimary: 'bg-primary-600 text-white hover:bg-primary-700 shadow-md shadow-primary-900/10',
    btnGhost: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900',
    flash: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    link: 'text-primary-700 hover:text-primary-800 underline decoration-primary-400/60',
    overlay: 'bg-slate-900/40',
};

function readStoredTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
}

export function useAdminTheme() {
    const theme = ref(readStoredTheme());
    const isDark = computed(() => theme.value === 'dark');

    watch(theme, (value) => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, value);
        }
    });

    function toggleTheme() {
        theme.value = isDark.value ? 'light' : 'dark';
    }

    const shell = computed(() => {
        const chrome = isDark.value ? darkChrome : lightChrome;

        const surfaces = isDark.value
            ? {
                  card: 'rounded-2xl border border-slate-700/80 bg-slate-800 shadow-xl shadow-black/30 ring-1 ring-slate-700/50',
                  cardTitle: 'text-white',
                  cardMuted: 'text-slate-400',
                  cardBody: 'text-slate-200',
                  input: 'border-slate-600 bg-slate-900 text-white placeholder:text-slate-500 focus:border-primary-500 focus:ring-primary-500/30',
                  tableHead: 'bg-slate-900/80 text-slate-300',
                  tableRow: 'text-slate-200',
                  tableDivide: 'divide-slate-700',
              }
            : {
                  card: 'rounded-2xl border border-slate-200 bg-white shadow-lg shadow-slate-300/40 ring-1 ring-slate-100',
                  cardTitle: 'text-slate-900',
                  cardMuted: 'text-slate-600',
                  cardBody: 'text-slate-800',
                  input: 'border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:ring-primary-500/25',
                  tableHead: 'bg-slate-50 text-slate-700',
                  tableRow: 'text-slate-800',
                  tableDivide: 'divide-slate-200',
              };

        return {
            ...chrome,
            ...surfaces,
            panel: surfaces.card,
            title: chrome.canvasTitle,
            muted: chrome.canvasMuted,
            label: chrome.canvasLabel,
        };
    });

    const chartMode = computed(() => (isDark.value ? 'dark' : 'light'));

    return { theme, isDark, toggleTheme, setTheme: (v) => { theme.value = v === 'dark' ? 'dark' : 'light'; }, shell, chartMode };
}

export function provideAdminTheme() {
    const api = useAdminTheme();
    provide(INJECT_KEY, api);

    return api;
}

export function useInjectedAdminTheme() {
    return inject(INJECT_KEY, null) ?? useAdminTheme();
}
