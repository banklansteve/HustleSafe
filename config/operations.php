<?php

return [
    'dispute_staff_ruling_max_minor' => (int) env('OPERATIONS_DISPUTE_RULING_MAX_MINOR', 5_000_000_00),

    'verification_queue' => [
        'default_assignment_range_days' => (int) env('OPERATIONS_VERIFICATION_ASSIGNMENT_RANGE_DAYS', 30),
        'max_assignment_range_days' => 365,
        'online_window_minutes' => (int) env('OPERATIONS_VERIFICATION_ONLINE_WINDOW_MINUTES', 5),
        'per_page' => 25,
    ],

    'payment_request_types' => [
        'hold_payout',
        'release_payout',
        'refund',
    ],

    'notification_categories' => [
        'assignment' => 'Assignments',
        'dispute' => 'Disputes',
        'kyc' => 'KYC & verification',
        'support' => 'CS & support',
        'referral' => 'Referrals',
        'task' => 'Tasks & reminders',
        'quality' => 'Quality & trust',
        'payment' => 'Payments',
        'system' => 'System',
        'team_chat' => 'Team chat',
        'hr' => 'HR & people ops',
    ],

    'notification_events' => [
        'task_assigned' => ['category' => 'assignment', 'default_in_app' => true, 'default_email' => true],
        'dispute_escalated' => ['category' => 'dispute', 'default_in_app' => true, 'default_email' => true],
        'kyc_ready' => ['category' => 'kyc', 'default_in_app' => true, 'default_email' => false],
        'cs_chat_waiting' => ['category' => 'support', 'default_in_app' => true, 'default_email' => false],
        'admin_referral' => ['category' => 'referral', 'default_in_app' => true, 'default_email' => true],
        'task_overdue' => ['category' => 'task', 'default_in_app' => true, 'default_email' => true],
        'quality_alert' => ['category' => 'quality', 'default_in_app' => true, 'default_email' => false],
        'watchlist_urgent' => ['category' => 'quality', 'default_in_app' => true, 'default_email' => false],
        'watchlist_tier_change' => ['category' => 'quality', 'default_in_app' => true, 'default_email' => false],
        'payment_exception' => ['category' => 'payment', 'default_in_app' => true, 'default_email' => true],
        'team_chat_message' => ['category' => 'team_chat', 'default_in_app' => true, 'default_email' => false],
        'hr_update' => ['category' => 'hr', 'default_in_app' => true, 'default_email' => true],
    ],

    'quality_thresholds' => [
        'min_rating' => 3.8,
        'max_dispute_rate_percent' => 15,
        'min_completion_rate_percent' => 70,
        'max_proposal_removal_rate_percent' => 20,
    ],

    'onboarding_scenarios' => [
        'client_inactivity_no_quest' => [
            'label' => 'Client · 15-day inactivity (no Quest)',
            'template_subject' => 'Need help posting your first Quest?',
            'template_body' => "Hi :name,\n\nWe noticed you joined HustleSafe but haven't published a Quest yet. I can help you draft a clear brief and choose the right category so freelancers respond faster.\n\nReply if you'd like a quick walkthrough.",
        ],
        'client_never_quest_creation' => [
            'label' => 'Client · never started Quest creation',
            'template_subject' => 'Let us help you post your first Quest',
            'template_body' => "Hi :name,\n\nYou haven't started creating a Quest yet. I can walk you through the wizard step by step — it only takes a few minutes.\n\nWould you like help getting started?",
        ],
        'client_quest_stuck_before_budget' => [
            'label' => 'Client · stuck before budget step',
            'template_subject' => 'Finish your Quest draft',
            'template_body' => "Hi :name,\n\nYou started a Quest but haven't added a budget yet. Clients who complete this step get proposals much faster.\n\nTell me if you'd like help choosing a fair budget range.",
        ],
        'client_quest_stuck_unpublished' => [
            'label' => 'Client · draft ready but not published',
            'template_subject' => 'Publish your Quest when you are ready',
            'template_body' => "Hi :name,\n\nYour Quest draft looks almost ready to go live. Publishing lets verified freelancers start sending proposals.\n\nReply if you want a quick review before you publish.",
        ],
        'client_retention_no_second_quest' => [
            'label' => 'Client · retention (no second Quest)',
            'template_subject' => 'Ready for your next Quest?',
            'template_body' => "Hi :name,\n\nYou published your first Quest a while ago — great start. Many clients post a follow-up Quest to keep momentum with freelancers they liked.\n\nI can help you scope a second project if useful.",
        ],
        'freelancer_inactivity_no_proposal' => [
            'label' => 'Freelancer · 15-day inactivity (no proposal)',
            'template_subject' => 'Ready to land your first Quest?',
            'template_body' => "Hi :name,\n\nYour profile is live — the next step is sending a strong proposal. I can suggest Quests that match your skills and review your pitch.\n\nWould you like tailored Quest picks?",
        ],
        'freelancer_never_viewed_quest' => [
            'label' => 'Freelancer · never browsed Quests',
            'template_subject' => 'Discover Quests that match your skills',
            'template_body' => "Hi :name,\n\nWe have active Quests in your categories. Browsing and saving a few is the fastest way to find work that fits.\n\nReply and I will point you to good matches.",
        ],
        'freelancer_viewed_no_proposal' => [
            'label' => 'Freelancer · viewed Quests, no proposal',
            'template_subject' => 'Turn your saved Quests into proposals',
            'template_body' => "Hi :name,\n\nYou have been exploring Quests — nice. The next step is a focused proposal so clients can see your approach.\n\nI can help you draft your first pitch if you want.",
        ],
        'freelancer_proposal_stuck_unsubmitted' => [
            'label' => 'Freelancer · proposal started, not submitted',
            'template_subject' => 'Complete your proposal draft',
            'template_body' => "Hi :name,\n\nYou started a proposal but have not submitted it yet. Completed proposals stand out much more to clients.\n\nTell me if you are stuck on pricing or scope wording.",
        ],
        'freelancer_retention_no_second_proposal' => [
            'label' => 'Freelancer · retention (no second proposal)',
            'template_subject' => 'Keep your momentum going',
            'template_body' => "Hi :name,\n\nYou submitted your first proposal — well done. Sending another targeted proposal this week often leads to faster wins.\n\nWant help picking your next Quest?",
        ],
        'quest_draft_abandoned' => [
            'label' => 'Abandoned Quest draft (48h+)',
            'template_subject' => 'Your Quest draft is waiting',
            'template_body' => "Hi :name,\n\nYou have a Quest draft that has been idle for a couple of days. I can help you finish and publish it.\n\nReply if you want a quick review.",
        ],
        'proposal_draft_abandoned' => [
            'label' => 'Abandoned proposal draft (48h+)',
            'template_subject' => 'Finish your proposal draft',
            'template_body' => "Hi :name,\n\nYou have a proposal draft that has not been submitted. I can help tighten your pitch or pricing before you send it.\n\nReply when you are ready.",
        ],
        'quest_listing_expiring_no_shortlist' => [
            'label' => 'Quest · expiring soon, client inactive',
            'template_subject' => 'Your quest closes soon — need help shortlisting?',
            'template_body' => "Hi :name,\n\nYour quest is approaching its proposal deadline and we have not seen you shortlist anyone yet. A quick review now helps you award before the listing closes.\n\nReply if you would like help comparing proposals or extending once with a reason.",
        ],
        'client_proposals_no_shortlist_5d' => [
            'label' => 'Client · proposals but no shortlist',
            'template_subject' => 'Ready to shortlist on your quest?',
            'template_body' => "Hi :name,\n\nYou have received proposals but have not shortlisted anyone yet. Shortlisting up to five favourites makes comparison easy and signals interest to strong freelancers.\n\nReply if you want help picking your shortlist.",
        ],
    ],

    'proactive_outreach' => [
        'freelancer_kyc_no_proposal_days' => 14,
        'client_no_quest_days' => 21,
        'awarded_no_escrow_hours' => 48,
        'dispute_no_evidence_hours' => 72,
        'rating_drop_threshold' => 0.5,
        'rating_drop_window_days' => 14,
        'quest_listing_expiring_soon_days' => 5,
        'quest_client_inactive_days' => 5,

        'situations' => [
            'freelancer_kyc_no_proposal_14d' => [
                'label' => 'Freelancer · KYC complete, no proposal (14d)',
                'hint' => 'Verified freelancer who has not submitted a proposal',
                'category' => 'retention',
                'priority' => 'medium',
                'priority_score' => 55,
                'default_template_slug' => 'freelancer-kyc-no-proposal',
            ],
            'client_no_quest_21d' => [
                'label' => 'Client · no Quest posted (21d)',
                'hint' => 'Registered client who has not published a Quest',
                'category' => 'retention',
                'priority' => 'medium',
                'priority_score' => 50,
                'default_template_slug' => 'client-no-quest-posted',
            ],
            'awarded_no_escrow_funded' => [
                'label' => 'Awarded · escrow not funded',
                'hint' => 'Client awarded a freelancer but escrow remains unfunded',
                'category' => 'escrow',
                'priority' => 'high',
                'priority_score' => 80,
                'default_template_slug' => 'quest-awarded-no-escrow',
            ],
            'freelancer_rating_drop' => [
                'label' => 'Freelancer · sudden rating drop',
                'hint' => 'Recent reviews pulled average rating down sharply',
                'category' => 'quality',
                'priority' => 'high',
                'priority_score' => 70,
                'default_template_slug' => 'freelancer-rating-drop-coaching',
            ],
            'dispute_open_no_evidence' => [
                'label' => 'Dispute · no evidence submitted',
                'hint' => 'Open dispute with no evidence uploaded after deadline window',
                'category' => 'dispute',
                'priority' => 'high',
                'priority_score' => 75,
                'default_template_slug' => 'dispute-no-evidence',
            ],
            'off_platform_payment_flagged' => [
                'label' => 'Off-platform payment attempt',
                'hint' => 'Conversation flagged for off-platform payment solicitation',
                'category' => 'trust',
                'priority' => 'urgent',
                'priority_score' => 90,
                'default_template_slug' => 'off-platform-payment-warning',
            ],
            'quest_listing_expiring_no_shortlist' => [
                'label' => 'Quest · expiring soon, no shortlist, client inactive',
                'hint' => 'Open quest nearing proposal deadline with no shortlist and no client activity in 5 days',
                'category' => 'retention',
                'priority' => 'high',
                'priority_score' => 72,
                'default_template_slug' => 'quest-listing-expiring-no-shortlist',
            ],
            'client_proposals_no_shortlist_5d' => [
                'label' => 'Client · proposals received, no shortlist (5d+)',
                'hint' => 'Client has proposals but has not shortlisted — may need a nudge or has a question',
                'category' => 'retention',
                'priority' => 'high',
                'priority_score' => 68,
                'default_template_slug' => 'client-proposals-no-shortlist',
            ],
            'quest_journey_low_proposal_quality' => [
                'label' => 'Quest · low proposal quality (journey survey)',
                'hint' => 'Client rated proposal quality below satisfied after quest completion',
                'category' => 'quality',
                'priority' => 'high',
                'priority_score' => 78,
                'default_template_slug' => null,
            ],
            'freelancer_low_payment_confidence' => [
                'label' => 'Freelancer · low payment confidence (journey survey)',
                'hint' => 'Awarded freelancer reported uncertain payment security — proactive support follow-up',
                'category' => 'trust',
                'priority' => 'high',
                'priority_score' => 76,
                'default_template_slug' => null,
            ],
        ],
    ],
];
