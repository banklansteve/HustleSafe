/**
 * Client-side checks aligned with QuestWizardStepValidator / StoreQuestRequest
 * so the wizard stays offline until final Inertia submit.
 */

const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

function todayYmd() {
    const d = new Date();

    return d.toISOString().slice(0, 10);
}

function isLeafInTree(categoryTree, leafId) {
    if (!leafId) {
        return false;
    }

    return categoryTree.some((p) => (p.children || []).some((c) => Number(c.id) === Number(leafId)));
}

function lgaInState(locations, stateId, lgaId) {
    if (!stateId || !lgaId) {
        return false;
    }
    const s = locations.find((x) => Number(x.id) === Number(stateId));

    return !!(s?.local_governments || []).some((lg) => Number(lg.id) === Number(lgaId));
}

/**
 * @param {number} step 1..6
 * @param {object} deps
 * @returns {{ ok: boolean, errors: Record<string, string> }}
 */
export function validateQuestCreateStep(step, deps) {
    const errors = {};
    const { form, fieldProfile, categoryTree, locations, maxBudgetMinor } = deps;

    if (step === 1) {
        if (!form.quest_category_id || !isLeafInTree(categoryTree, form.quest_category_id)) {
            errors.quest_category_id = 'Choose a valid subcategory.';
        }
        if (!String(form.title || '').trim()) {
            errors.title = 'Add a short title.';
        } else if (String(form.title).length > 200) {
            errors.title = 'Title must be 200 characters or fewer.';
        }
        if (!String(form.description || '').trim()) {
            errors.description = 'Describe the quest so freelancers understand the brief.';
        } else if (String(form.description).length > 50000) {
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
        if (form.traffic_source && String(form.traffic_source).length > 128) {
            errors.traffic_source = 'Traffic source is too long.';
        }
    }

    if (step === 3) {
        if (!form.state_id) {
            errors.state_id = 'Select a state.';
        }
        if (!form.local_government_id || !lgaInState(locations, form.state_id, form.local_government_id)) {
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
        if (!form.estimated_completion_days || form.estimated_completion_days < 1 || form.estimated_completion_days > 365) {
            errors.estimated_completion_days = 'Pick a completion window between 1 and 365 days.';
        }
        const edd = String(form.estimated_delivery_date || '').trim();
        if (edd && edd < todayYmd()) {
            errors.estimated_delivery_date = 'Delivery date must be today or later.';
        }
        const b = Number(form.budget_amount_minor);
        if (!Number.isFinite(b) || b < 10000 || b > maxBudgetMinor) {
            errors.budget_amount_minor = `Budget must be between ₦${(10000 / 100).toLocaleString('en-NG')} and ₦${(maxBudgetMinor / 100).toLocaleString('en-NG')}.`;
        }
    }

    if (step === 5) {
        if (fieldProfile.show_hourly_fields && form.project_type === 'hourly') {
            const h = Number(form.estimated_hours);
            if (!Number.isFinite(h) || h < 1 || h > 2000) {
                errors.estimated_hours = 'Add estimated hours (1–2000) for hourly work.';
            }
        }
        if (fieldProfile.show_team_size && !form.team_size) {
            errors.team_size = 'Choose solo or small team.';
        }
    }

    if (step === 6) {
        if (!form.promotion_tier) {
            errors.promotion_tier = 'Pick a listing promotion tier.';
        }
        const exp = form.auto_listing_expiry_days;
        if (exp != null && exp !== '' && (Number(exp) < 1 || Number(exp) > 90)) {
            errors.auto_listing_expiry_days = 'Expiry must be between 1 and 90 days.';
        }
        const mo = form.max_offers;
        if (mo != null && mo !== '' && (Number(mo) < 1 || Number(mo) > 200)) {
            errors.max_offers = 'Max proposals must be between 1 and 200.';
        }
        const slug = String(form.slug || '').trim();
        if (slug && (!SLUG_RE.test(slug) || slug.length > 120)) {
            errors.slug = 'Use lowercase letters, numbers, and single hyphens only (max 120 characters).';
        }
        if (form.visibility === 'invite_only' && (!Array.isArray(form.tagged_freelancer_ids) || form.tagged_freelancer_ids.length < 1)) {
            errors.tagged_freelancer_ids = 'Invite-only quests need at least one tagged freelancer.';
        }
    }

    return { ok: Object.keys(errors).length === 0, errors };
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
