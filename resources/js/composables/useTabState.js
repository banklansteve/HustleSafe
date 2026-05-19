import { onBeforeUnmount, ref, watch } from 'vue';

function readParam(name) {
    if (typeof window === 'undefined') {
        return null;
    }

    return new URLSearchParams(window.location.search).get(name);
}

function writeParams(updates, mode = 'push') {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    Object.entries(updates).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });

    const method = mode === 'replace' ? 'replaceState' : 'pushState';
    window.history[method]({ ...window.history.state, adminTabState: true }, '', url.toString());
}

export function useTabState(validTabs, defaultTab, options = {}) {
    const param = options.param || 'tab';
    const extraParams = () => (typeof options.extraParams === 'function' ? options.extraParams() : (options.extraParams || {}));
    const valid = new Set(validTabs);
    const initial = readParam(param);
    const activeTab = ref(valid.has(initial) ? initial : defaultTab);
    let internalUpdate = false;

    if (initial && !valid.has(initial)) {
        writeParams({ [param]: defaultTab, ...extraParams() }, 'replace');
    } else if (!initial && options.writeDefault !== false) {
        writeParams({ [param]: defaultTab, ...extraParams() }, 'replace');
    }

    function setTab(tab) {
        if (!valid.has(tab) || tab === activeTab.value) {
            return;
        }
        activeTab.value = tab;
    }

    const stop = watch(activeTab, (value) => {
        if (internalUpdate) {
            internalUpdate = false;
            return;
        }
        writeParams({ [param]: value, ...extraParams() }, options.historyMode || 'push');
    });

    function onPopState() {
        const next = readParam(param);
        internalUpdate = true;
        activeTab.value = valid.has(next) ? next : defaultTab;
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('popstate', onPopState);
    }

    onBeforeUnmount(() => {
        stop();
        if (typeof window !== 'undefined') {
            window.removeEventListener('popstate', onPopState);
            if (options.clearOnUnmount) {
                writeParams({ [param]: null, ...Object.fromEntries(Object.keys(extraParams()).map((key) => [key, null])) }, 'replace');
            }
        }
    });

    return { activeTab, setTab };
}
