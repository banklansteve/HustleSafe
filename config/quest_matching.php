<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weighted match score (each component 0–100)
    |--------------------------------------------------------------------------
    */
    'weights' => [
        'location' => 0.40,
        'skills' => 0.25,
        'budget' => 0.15,
        'tier_quality' => 0.15,
        'activity' => 0.05,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote / online-friendly quests (location does not affect fit)
    |--------------------------------------------------------------------------
    |
    | Used when the client chose remote-friendly hiring or the category is
    | normally delivered online (e.g. software, design). Skills and profile
    | quality matter more than state/LGA overlap.
    |
    */
    'remote_weights' => [
        'location' => 0.0,
        'skills' => 0.45,
        'budget' => 0.15,
        'tier_quality' => 0.25,
        'activity' => 0.15,
    ],

    'remote_location_score' => 100,

    'skills_minimum_ratio' => 0.50,

    'location_tiers' => [
        'same_lga' => 100,
        'same_state' => 70,
        'different_state' => 40,
        'unknown' => 50,
    ],

    'match_quality_bands' => [
        ['min' => 90, 'label' => 'Perfect match', 'stars' => 5],
        ['min' => 75, 'label' => 'Great match', 'stars' => 4],
        ['min' => 60, 'label' => 'Good match', 'stars' => 3],
        ['min' => 50, 'label' => 'Possible match', 'stars' => 2],
        ['min' => 0, 'label' => 'Low match', 'stars' => 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hard gates
    |--------------------------------------------------------------------------
    */
    'active_job_limits_by_level' => [
        0 => 0,
        1 => 2,
        2 => 3,
        3 => 5,
        4 => 8,
        5 => 12,
    ],

    'active_job_statuses' => [
        'assigned',
        'in_progress',
        'paused',
        'pending_review',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bonuses & penalties (added to raw total before clamp 0–100)
    |--------------------------------------------------------------------------
    */
    'niche_specialization_min_completions' => 10,
    'niche_specialization_bonus_points' => 10,

    'urgency_days' => 3,
    'urgency_quick_jobs_min' => 3,
    'urgency_quick_delivery_max_days' => 5,
    'urgency_bonus_points' => 5,

    'dispute_penalty_per_incident' => 8,
    'dispute_penalty_cap' => 24,

    'client_follow_boost_points' => 8,

    'freelancer_pro_bonus_points' => 8,

    'metrics_refresh_hours' => 6,

    'freelancer_feed_candidate_limit' => 300,

    'client_recommendations_limit' => 5,

    'client_recommendations_more_limit' => 25,

    'quest_invite_freelancer_max' => 100,

];
