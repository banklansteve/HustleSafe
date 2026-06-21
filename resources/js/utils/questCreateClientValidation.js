/**
 * Client-side checks aligned with QuestWizardStepValidator / StoreQuestRequest
 * so the wizard stays offline until final Inertia submit.
 */

function todayYmdLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

function isLeafInTree(categoryTree, leafId) {
    const lid = Number(leafId);
    if (!Number.isFinite(lid) || lid < 1) {
        return false;
    }

    return categoryTree.some((p) => (p.children || []).some((c) => Number(c.id) === lid));
}

function lgaInState(locations, stateId, lgaId) {
    const sid = Number(stateId);
    const lid = Number(lgaId);
    if (!Number.isFinite(sid) || !Number.isFinite(lid)) {
        return false;
    }
    const s = locations.find((x) => Number(x.id) === sid);

    return !!(s?.local_governments || []).some((lg) => Number(lg.id) === lid);
}

/**
 * @param {number} step 1..6
 * @param {object} deps
 * @returns {{ ok: boolean, errors: Record<string, string> }}
 */
export function validateQuestCreateStep(step, deps) {
    const errors = {};
    const { form, fieldProfile, categoryTree, locations, maxBudgetMinor, minBudgetMinor = 10000 } = deps;

    if (step === 1) {
        if (!form.quest_category_id || !isLeafInTree(categoryTree, form.quest_category_id)) {
            errors.quest_category_id = 'Choose a valid subcategory.';
        }
        if (!String(form.title || '').trim()) {
            errors.title = 'Add a short title.';
        } else if (String(form.title).length > 200) {
            errors.title = 'Title must be 200 characters or fewer.';
        }
        const descPlain = String(form.description || '').trim();
        if (!descPlain) {
            errors.description = 'Describe the quest so freelancers understand the brief.';
        } else if (descPlain.length > 50000) {
            errors.description = 'Description is too long.';
        }
    }

    if (step === 2) {
        if (!form.visibility) {
            errors.visibility = 'Pick job visibility.';
        }
        if (!form.freelancer_location_pref) {
            errors.freelancer_location_pref = 'Pick a location preference.';
        }
        if (fieldProfile.show_availability && !form.availability_need) {
            errors.availability_need = 'Select an availability expectation.';
        }
        if (form.traffic_source_key === 'other' && String(form.traffic_source_other || '').length > 128) {
            errors.traffic_source = 'Please keep your answer under 128 characters.';
        }
        if (form.traffic_source_key === 'other' && !String(form.traffic_source_other || '').trim()) {
            errors.traffic_source = 'Tell us where you heard about us.';
        }
    }

    if (step === 3) {
        const sid = Number(form.state_id);
        if (!Number.isFinite(sid) || sid < 1) {
            errors.state_id = 'Select a state.';
        }
        const lgid = Number(form.local_government_id);
        if (!Number.isFinite(lgid) || lgid < 1 || !lgaInState(locations, form.state_id, form.local_government_id)) {
            errors.local_government_id = 'Select a valid LGA for this state.';
        }
        if (!String(form.city || '').trim()) {
            errors.city = 'Add a city or area.';
        } else if (String(form.city).length > 160) {
            errors.city = 'City is too long.';
        }
    }

    if (step === 4) {
        if (!form.start_timing) {
            errors.start_timing = 'Choose when work should start.';
        }
        if (form.start_timing === 'scheduled' && !String(form.scheduled_start_date || '').trim()) {
            errors.scheduled_start_date = 'Pick a start date for a scheduled start.';
        }
        const days = Number(form.estimated_completion_days);
        if (!Number.isFinite(days) || days < 1 || days > 365) {
            errors.estimated_completion_days = 'Pick a completion window between 1 and 365 days.';
        }
        const edd = String(form.estimated_delivery_date || '').trim();
        if (edd && edd < todayYmdLocal()) {
            errors.estimated_delivery_date = 'Planned finish must be today or later.';
        }
        const ddl = String(form.delivery_deadline || '').trim();
        if (ddl && ddl < todayYmdLocal()) {
            errors.delivery_deadline = 'Delivery deadline must be today or later.';
        }
        if (ddl && edd && ddl < edd) {
            errors.delivery_deadline = 'Delivery deadline must be on or after the planned finish date.';
        }
        const b = Number(form.budget_amount_minor);
        const maxB = Number(maxBudgetMinor);
        const minB = Number(minBudgetMinor);
        if (!Number.isFinite(b) || b < minB || b > maxB) {
            errors.budget_amount_minor = `Budget must be between ₦${(minB / 100).toLocaleString('en-NG')} and ₦${(maxB / 100).toLocaleString('en-NG')}.`;
        }
    }

    if (step === 5) {
        if (fieldProfile.show_site_access) {
            if (!String(form.site_access_level || '').trim()) {
                errors.site_access_level = 'Choose how accessible the work location is.';
            }
            if (form.pets_on_site !== true && form.pets_on_site !== false) {
                errors.pets_on_site = 'Say whether pets are usually on-site.';
            }
        }
        if (fieldProfile.show_hourly_fields && form.project_type === 'hourly') {
            const h = Number(form.estimated_hours);
            if (!Number.isFinite(h) || h < 1 || h > 2000) {
                errors.estimated_hours = 'Add estimated hours (1–2000) for hourly work.';
            }
        }
        if (fieldProfile.show_team_size && !form.team_size) {
            errors.team_size = 'Choose how many people should work on this quest.';
        }
    }

    if (step === 6) {
        const bounds = deps.proposalDeadlineBounds ?? { min: 1, max: 60, default: 14 };
        const exp = form.auto_listing_expiry_days;
        if (exp == null || exp === '') {
            errors.auto_listing_expiry_days = `Choose how many days to accept proposals (default ${bounds.default}).`;
        } else if (Number(exp) < bounds.min || Number(exp) > bounds.max) {
            errors.auto_listing_expiry_days = `Proposal deadline must be between ${bounds.min} and ${bounds.max} days.`;
        }
        const mo = form.max_offers;
        if (mo != null && mo !== '' && (Number(mo) < 1 || Number(mo) > 200)) {
            errors.max_offers = 'Max proposals must be between 1 and 200.';
        }
        if (form.visibility === 'invite_only' && (!Array.isArray(form.tagged_freelancer_ids) || form.tagged_freelancer_ids.length < 1)) {
            errors.tagged_freelancer_ids = 'Invite-only quests need at least one tagged freelancer.';
        }
        if (form.engagement_mode === 'recurring_installment' && !form.contract_renewal_preference) {
            errors.contract_renewal_preference = 'Choose what should happen when the contract ends.';
        }
    }

    return { ok: Object.keys(errors).length === 0, errors };
}

/** @type {Record<string, number>} */
export const QUEST_CREATE_FIELD_STEP = {
    quest_category_id: 1,
    title: 1,
    description: 1,
    visibility: 2,
    freelancer_location_pref: 2,
    availability_need: 2,
    traffic_source: 2,
    state_id: 3,
    local_government_id: 3,
    city: 3,
    start_timing: 4,
    scheduled_start_date: 4,
    estimated_completion_days: 4,
    estimated_delivery_date: 4,
    delivery_deadline: 4,
    budget_amount_minor: 4,
    project_type: 5,
    estimated_hours: 5,
    team_size: 5,
    site_visits_allowed: 5,
    site_access_level: 5,
    pets_on_site: 5,
    pets_detail: 5,
    auto_listing_expiry_days: 6,
    max_offers: 6,
    tagged_freelancer_ids: 6,
    contract_renewal_preference: 6,
    files: 6,
    accepted_terms: 7,
    publish_now: 7,
};

/**
 * @param {Record<string, string|string[]>} errors
 */
export function firstQuestCreateErrorMessage(errors) {
    for (const value of Object.values(errors || {})) {
        if (typeof value === 'string' && value.trim()) {
            return value;
        }
        if (Array.isArray(value) && typeof value[0] === 'string' && value[0].trim()) {
            return value[0];
        }
    }

    return 'Your quest could not be saved. Review the highlighted fields and try again.';
}

/**
 * @param {string} field
 * @returns {number}
 */
export function questCreateStepForField(field) {
    const base = String(field || '').split('.')[0];

    return QUEST_CREATE_FIELD_STEP[field] ?? QUEST_CREATE_FIELD_STEP[base] ?? 7;
}

/**
 * Pick the earliest wizard step that contains a server validation error.
 *
 * @param {Record<string, string|string[]>} errors
 * @returns {{ message: string, step: number }}
 */
export function resolveQuestCreateSubmitErrors(errors) {
    const entries = Object.entries(errors || {});
    if (!entries.length) {
        return { message: '', step: 7 };
    }

    let step = 7;
    for (const [field] of entries) {
        step = Math.min(step, questCreateStepForField(field));
    }

    return {
        message: firstQuestCreateErrorMessage(errors),
        step,
    };
}

/**
 * @returns {number|null} first failing step 1-6, or null if all pass
 */
export function firstFailingQuestCreateStep(deps) {
    for (let s = 1; s <= 6; s += 1) {
        const { ok } = validateQuestCreateStep(s, deps);
        if (!ok) {
            return s;
        }
    }

    return null;
}
