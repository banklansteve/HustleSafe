import { formatClientDate, questCompletionSchedule } from '@/utils/questCompletionSchedule';

const START_TIMING_HINTS = {
    urgent_48h: 'Client wants work to start within 48 hours.',
    this_week: 'Client wants work to start this week.',
    next_two_weeks: 'Client wants work to start within the next two weeks.',
    flexible: 'Client is flexible on when work starts.',
    window_shopping: 'Client has not picked a firm start date yet.',
};

/**
 * Short, plain-English hints for proposal form fields based on what the client entered on the quest.
 *
 * @param {object} quest
 * @returns {Record<string, string|null>}
 */
export function buildQuestClientExpectationHints(quest) {
    const hints = {
        plannedStart: null,
        plannedFinish: null,
        duration: null,
        budget: null,
        revisions: null,
        progressReports: null,
    };

    if (!quest || typeof quest !== 'object') {
        return hints;
    }

    const scheduled = formatClientDate(quest.scheduled_start_date);
    if (scheduled) {
        hints.plannedStart = `Client expects work to start around ${scheduled}.`;
    } else if (quest.start_timing && START_TIMING_HINTS[quest.start_timing]) {
        hints.plannedStart = START_TIMING_HINTS[quest.start_timing];
    }

    const schedule = questCompletionSchedule(quest);
    const planned = formatClientDate(schedule?.planned_finish_date ?? quest.estimated_delivery_date);
    const hard = formatClientDate(schedule?.hard_deadline_date ?? quest.delivery_deadline);

    if (planned && hard) {
        hints.plannedFinish = planned === hard
            ? `Client target finish and hard deadline: ${planned}.`
            : `Client target finish ${planned}. Hard deadline (must not slip past): ${hard}.`;
    } else if (hard) {
        hints.plannedFinish = `Client set a hard delivery deadline of ${hard}.`;
    } else if (planned) {
        hints.plannedFinish = `Client target finish is around ${planned}.`;
    }

    const days = Number(quest.estimated_completion_days);
    if (Number.isFinite(days) && days > 0) {
        hints.duration = `Client listed about ${days} day${days === 1 ? '' : 's'} for this job.`;
    }

    if (Number(quest.budget_minor) > 0) {
        hints.budget = `Client budget cap is ${formatBudget(quest.budget_minor)}.`;
    }

    const revisionPref = specifiedPreference(quest.preferences, 'revision_rounds');
    if (revisionPref?.display_value) {
        const rounds = String(revisionPref.display_value).trim();
        hints.revisions = `Client included ${rounds} free revision round${rounds === '1' ? '' : 's'}.`;
    }

    const contactPref = specifiedPreference(quest.preferences, 'best_contact_time');
    if (contactPref?.display_value && contactPref.display_value !== 'Not specified') {
        hints.progressReports = `Client is usually reachable in-app: ${contactPref.display_value}.`;
    }

    const feedbackDays = specifiedPreference(quest.preferences, 'feedback_timeline_days');
    if (feedbackDays?.display_value) {
        const d = String(feedbackDays.display_value).trim();
        hints.progressReports = hints.progressReports
            ? `${hints.progressReports} They need about ${d} day${d === '1' ? '' : 's'} to reply each round.`
            : `Client needs about ${d} day${d === '1' ? '' : 's'} to give feedback each round.`;
    }

    return hints;
}

function formatBudget(minor) {
    const n = Math.round(Number(minor) || 0) / 100;

    return `₦${n.toLocaleString('en-NG')}`;
}

function specifiedPreference(preferences, key) {
    if (!Array.isArray(preferences)) {
        return null;
    }

    const row = preferences.find((p) => p.key === key && p.is_specified);

    return row ?? null;
}

export function clientSpecifiedRevisionRounds(quest) {
    return specifiedPreference(quest?.preferences, 'revision_rounds');
}
