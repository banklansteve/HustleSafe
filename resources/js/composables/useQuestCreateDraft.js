import { computed, onMounted, unref, watch } from 'vue';

const DRAFT_VERSION = 1;

/**
 * Persist wizard state to localStorage (files are not stored — user re-attaches after refresh).
 *
 * @param {import('vue').Ref<string>|string} storageKeyRef
 * @param {() => object} getSnapshotSource returns object with reactive deps (e.g. includes `form` from useForm)
 * @param {(data: object) => void} applySnapshot
 */
export function useQuestCreateDraft(storageKeyRef, getSnapshotSource, applySnapshot) {
    let timer = null;

    function key() {
        return unref(storageKeyRef);
    }

    function save() {
        const k = key();
        if (!k) {
            return;
        }
        try {
            const snap = buildSerializableSnapshot(getSnapshotSource());
            localStorage.setItem(k, JSON.stringify({ v: DRAFT_VERSION, ...snap }));
        } catch {
            /* quota or private mode */
        }
    }

    function debouncedSave() {
        window.clearTimeout(timer);
        timer = window.setTimeout(save, 450);
    }

    onMounted(() => {
        const k = key();
        if (!k) {
            return;
        }
        try {
            const raw = localStorage.getItem(k);
            if (!raw) {
                return;
            }
            const data = JSON.parse(raw);
            if (!data || data.v !== DRAFT_VERSION) {
                return;
            }
            applySnapshot(data);
        } catch {
            /* ignore corrupt */
        }
    });

    const watched = computed(() => JSON.stringify(buildSerializableSnapshot(getSnapshotSource())));
    watch([watched, () => key()], debouncedSave);

    function clearDraft() {
        window.clearTimeout(timer);
        try {
            const k = key();
            if (k) {
                localStorage.removeItem(k);
            }
        } catch {
            /* ignore */
        }
    }

    return { clearDraft, saveNow: save };
}

/**
 * Strip non-JSON / heavy pieces from snapshot source.
 */
function buildSerializableSnapshot(src) {
    const { form, ...rest } = src;
    const fd = typeof form?.data === 'function' ? form.data() : form;
    const { files: _files, accepted_terms: _acceptedTerms, ...formRest } = fd;

    return { ...rest, form: formRest };
}
