<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client edit window (after publish)
    |--------------------------------------------------------------------------
    |
    | How long an open quest can be edited by the client after it is first
    | published. After this time, updates are blocked (admins can still act).
    |
    */

    'client_edit_window_hours' => (int) env('QUEST_CLIENT_EDIT_WINDOW_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Default quest cover (no image uploads)
    |--------------------------------------------------------------------------
    |
    | Public path relative to the site root, passed to asset().
    |
    */

    'default_cover_asset' => env('QUEST_DEFAULT_COVER_ASSET', 'images/quest-cover-default.svg'),

    /*
    |--------------------------------------------------------------------------
    | Pre-proposal messaging thread cap
    |--------------------------------------------------------------------------
    */
    'thread_max_messages' => (int) env('QUEST_THREAD_MAX_MESSAGES', 120),

    /*
    |--------------------------------------------------------------------------
    | Quest message body length
    |--------------------------------------------------------------------------
    |
    | Pre-award: longer questions about the brief. After a proposal is accepted,
    | messages are capped tighter so the thread stays operational, not a full chat app.
    |
    */
    'thread_message_body_max_default' => (int) env('QUEST_THREAD_MESSAGE_BODY_MAX', 2000),

    'thread_message_body_max_after_accepted' => (int) env('QUEST_THREAD_MESSAGE_BODY_MAX_AFTER_ACCEPTED', 720),

    /*
    |--------------------------------------------------------------------------
    | Proposal VAT (percent of fee + materials + travel, before VAT/WHT)
    |--------------------------------------------------------------------------
    */
    'proposal_vat_percent' => (float) env('QUEST_PROPOSAL_VAT_PERCENT', 7.5),

    /*
    |--------------------------------------------------------------------------
    | Proposal edit window (freelancer, before client decision)
    |--------------------------------------------------------------------------
    */
    'proposal_freelancer_edit_hours' => (int) env('QUEST_PROPOSAL_FREELANCER_EDIT_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Display-only platform fee % (copy / emails — billing integration separate)
    |--------------------------------------------------------------------------
    */
    'platform_fee_percent_display' => (float) env('QUEST_PLATFORM_FEE_PERCENT_DISPLAY', 5),
];
