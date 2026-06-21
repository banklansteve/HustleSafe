/**
 * Display helpers for quest completion schedule payloads from the API.
 *
 * @param {object|null|undefined} schedule
 * @returns {string|null}
 */
export function completionScheduleSummary(schedule) {
    if (!schedule || typeof schedule !== 'object') {
        return null;
    }

    return schedule.summary || null;
}

/**
 * @param {object|null|undefined} quest
 * @returns {object|null}
 */
export function questCompletionSchedule(quest) {
    if (quest?.completion_schedule && typeof quest.completion_schedule === 'object') {
        return quest.completion_schedule;
    }

    const planned = quest?.estimated_delivery_date || null;
    const hard = quest?.delivery_deadline || null;

    if (!planned && !hard) {
        return null;
    }

    return {
        planned_finish_date: planned,
        hard_deadline_date: hard,
        planned_finish_label: 'Planned finish',
        hard_deadline_label: 'Delivery deadline',
        summary: planned && hard
            ? (planned === hard
                ? `Finish target & deadline: ${formatClientDate(planned)}`
                : `Target finish ${formatClientDate(planned)} · Hard deadline ${formatClientDate(hard)}`)
            : hard
                ? `Delivery deadline: ${formatClientDate(hard)}`
                : `Planned finish: ${formatClientDate(planned)}`,
    };
}

/**
 * @param {string|null|undefined} iso
 * @returns {string|null}
 */
export function formatClientDate(iso) {
    if (!iso || typeof iso !== 'string') {
        return null;
    }

    try {
        return new Date(`${iso}T12:00:00`).toLocaleDateString('en-NG', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return iso;
    }
}
