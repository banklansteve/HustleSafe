<?php

return [
    'email_delay_hours' => (int) env('QUEST_JOURNEY_SURVEY_EMAIL_DELAY_HOURS', 2),
    'link_ttl_days' => 7,

    'reminders' => [
        'after_initial_hours' => [24, 72],
        'before_expiry_hours' => 6,
    ],

    'reminder_copy' => [
        '24h' => [
            'subject' => 'Quick reminder — your feedback on ":quest"',
            'opener' => 'We sent a short anonymous survey about your recent quest experience. If you have two minutes, we\'d still love to hear from you.',
            'headline' => 'Still time to share your thoughts',
        ],
        '72h' => [
            'subject' => 'Your voice matters — ":quest" feedback',
            'opener' => 'Your feedback helps us improve HustleSafe for clients and freelancers alike. Tap an answer below to pick up where you left off — no login needed.',
            'headline' => 'We\'re still listening',
        ],
        'before_expiry' => [
            'subject' => 'Last chance — ":quest" survey closes soon',
            'opener' => 'This anonymous survey closes in about six hours. One tap below saves your first answer and opens the rest — thank you for helping us improve.',
            'headline' => 'Survey closing soon',
        ],
    ],

    'cohorts' => [
        'client_completed' => [
            'first_question_key' => 'proposal_quality',
            'email_subject' => '":quest" is complete — how did we do?',
            'email_opener' => 'Your quest is complete and payment has been released. We\'d love three minutes of your feedback on the full experience.',
        ],
        'freelancer_awarded' => [
            'first_question_key' => 'payment_release_smooth',
            'email_subject' => 'You\'ve been paid — tell us how it went',
            'email_opener' => 'Your payment for ":quest" has been released. We\'d love to hear about your experience from start to finish.',
        ],
        'freelancer_rejected' => [
            'first_question_key' => 'brief_adequacy',
            'email_subject' => 'Your proposal for ":quest" — a quick question from us',
            'email_opener' => 'You weren\'t selected for this one, but your proposal was seen. We\'d love two minutes of your honest feedback to help us make the platform work better for you.',
        ],
    ],

    'questions' => [
        'client_completed' => [
            ['key' => 'proposal_quality', 'type' => 'choice', 'email_embedded' => true, 'label' => 'How satisfied were you with the quality and relevance of proposals you received?', 'options' => [
                ['value' => 'very_satisfied', 'label' => 'Very satisfied'],
                ['value' => 'satisfied', 'label' => 'Satisfied'],
                ['value' => 'neutral', 'label' => 'Neutral'],
                ['value' => 'dissatisfied', 'label' => 'Dissatisfied'],
                ['value' => 'very_dissatisfied', 'label' => 'Very dissatisfied'],
            ]],
            ['key' => 'quest_create_easy', 'type' => 'ease', 'label' => 'How easy was it to create and publish this quest?'],
            ['key' => 'wizard_clarity', 'type' => 'agree', 'label' => 'Did the quest wizard help you set a clear budget, timeline, and scope?'],
            ['key' => 'brief_confidence', 'type' => 'agree', 'label' => 'Before proposals arrived, did you feel confident freelancers would understand what you needed?'],
            ['key' => 'review_proposals_easy', 'type' => 'ease', 'label' => 'How easy was it to review and compare proposals?'],
            ['key' => 'shortlist_helpful', 'type' => 'choice', 'label' => 'Did the shortlisting and proposal review process help you make a confident decision?', 'options' => [
                ['value' => 'yes_very', 'label' => 'Yes, very much'],
                ['value' => 'somewhat', 'label' => 'Somewhat'],
                ['value' => 'not_really', 'label' => 'Not really'],
                ['value' => 'didnt_use', 'label' => 'I didn\'t use it'],
            ]],
            ['key' => 'award_clear', 'type' => 'clarity', 'label' => 'How clear was the award step (scope, price, deadline confirmation)?'],
            ['key' => 'escrow_easy', 'type' => 'ease', 'label' => 'How easy was it to fund escrow and understand when money would be held vs released?'],
            ['key' => 'escrow_fair', 'type' => 'agree', 'label' => 'Did escrow rules (milestones, release timing, fees) feel fair and understandable before you paid?'],
            ['key' => 'communication_smooth', 'type' => 'ease', 'label' => 'How smooth was communication with your freelancer during the work? (This is about the process, not rating the person.)'],
            ['key' => 'completion_clear', 'type' => 'clarity', 'label' => 'How clear was the process to mark work complete and release funds?'],
            ['key' => 'support_needed', 'type' => 'choice', 'label' => 'Did you need to contact support at any point during this quest?', 'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ]],
            ['key' => 'support_satisfaction', 'type' => 'satisfaction', 'label' => 'How satisfied were you with the support you received?', 'show_when' => ['support_needed' => 'yes']],
            ['key' => 'dispute_fair', 'type' => 'fairness', 'label' => 'If you opened or joined a dispute, how fairly and clearly was it handled?', 'requires_dispute' => true],
            ['key' => 'dispute_clear', 'type' => 'agree', 'label' => 'Did you understand what was happening at each stage of the dispute?', 'requires_dispute' => true],
            ['key' => 'best_part', 'type' => 'choice', 'label' => 'What was the best part of this quest on HustleSafe?', 'options' => [
                ['value' => 'posting', 'label' => 'Posting the quest'],
                ['value' => 'proposals', 'label' => 'Reviewing proposals'],
                ['value' => 'award', 'label' => 'Awarding a freelancer'],
                ['value' => 'escrow', 'label' => 'Escrow & payment'],
                ['value' => 'delivery', 'label' => 'Delivery & communication'],
                ['value' => 'support', 'label' => 'Support'],
                ['value' => 'other', 'label' => 'Something else'],
            ]],
            ['key' => 'one_improvement', 'type' => 'text', 'label' => 'What one thing would have made this journey easier?', 'optional' => true, 'max' => 500],
            ['key' => 'post_again_likelihood', 'type' => 'choice', 'label' => 'How likely are you to post another quest on this platform?', 'options' => [
                ['value' => 'definitely', 'label' => 'Definitely'],
                ['value' => 'probably', 'label' => 'Probably'],
                ['value' => 'not_sure', 'label' => 'Not sure'],
                ['value' => 'unlikely', 'label' => 'Unlikely'],
            ]],
        ],
        'freelancer_awarded' => [
            ['key' => 'payment_release_smooth', 'type' => 'choice', 'email_embedded' => true, 'label' => 'How smooth was the payment release experience once the job was marked complete?', 'options' => [
                ['value' => 'very_smooth', 'label' => 'Very smooth'],
                ['value' => 'smooth', 'label' => 'Smooth'],
                ['value' => 'slightly_frustrating', 'label' => 'Slightly frustrating'],
                ['value' => 'very_frustrating', 'label' => 'Very frustrating'],
            ]],
            ['key' => 'find_quest_easy', 'type' => 'ease', 'label' => 'How easy was it to find this quest and decide it was a good fit?'],
            ['key' => 'brief_clear', 'type' => 'clarity', 'label' => 'How clear was the client\'s brief when you wrote your proposal?'],
            ['key' => 'proposal_form_easy', 'type' => 'ease', 'label' => 'How easy was the proposal form (pricing, timeline, scope)?'],
            ['key' => 'clarify_helpful', 'type' => 'choice', 'label' => 'Did the Q&A / clarify flow help you submit a stronger proposal?', 'requires_clarification' => true, 'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'somewhat', 'label' => 'Somewhat'],
                ['value' => 'no', 'label' => 'No'],
            ]],
            ['key' => 'award_process_clear', 'type' => 'clarity', 'label' => 'How clear was it when the client moved toward award and acceptance?'],
            ['key' => 'accept_award_smooth', 'type' => 'ease', 'label' => 'How smooth was accepting the award (terms, deadline, escrow expectations)?'],
            ['key' => 'escrow_funded_clear', 'type' => 'agree', 'label' => 'Did you understand when escrow was funded and what that meant for starting work?'],
            ['key' => 'delivery_easy', 'type' => 'ease', 'label' => 'How easy was it to deliver work and communicate progress on the platform?'],
            ['key' => 'handover_clear', 'type' => 'clarity', 'label' => 'How clear was the completion / handover step before payment release?'],
            ['key' => 'fees_explained', 'type' => 'agree', 'label' => 'Were platform fees and payout timing explained clearly enough?'],
            ['key' => 'scope_changed', 'type' => 'choice', 'label' => 'Did the agreed scope or requirements change significantly after the quest was awarded?', 'options' => [
                ['value' => 'no_changes', 'label' => 'No changes'],
                ['value' => 'minor', 'label' => 'Minor changes'],
                ['value' => 'significant', 'label' => 'Significant changes'],
                ['value' => 'completely', 'label' => 'It changed completely'],
            ]],
            ['key' => 'payment_confidence', 'type' => 'choice', 'label' => 'How confident did you feel that your payment was secure throughout the project?', 'options' => [
                ['value' => 'very_confident', 'label' => 'Very confident'],
                ['value' => 'confident', 'label' => 'Confident'],
                ['value' => 'somewhat_uncertain', 'label' => 'Somewhat uncertain'],
                ['value' => 'not_confident', 'label' => 'Not confident at all'],
            ]],
            ['key' => 'dispute_fair', 'type' => 'fairness', 'label' => 'If a dispute occurred, did you feel the process was fair and transparent?', 'requires_dispute' => true],
            ['key' => 'best_part', 'type' => 'choice', 'label' => 'What was the best part of this quest journey for you?', 'options' => [
                ['value' => 'finding', 'label' => 'Finding the quest'],
                ['value' => 'proposal', 'label' => 'Writing my proposal'],
                ['value' => 'award', 'label' => 'Award & acceptance'],
                ['value' => 'escrow', 'label' => 'Escrow clarity'],
                ['value' => 'delivery', 'label' => 'Delivery & communication'],
                ['value' => 'payout', 'label' => 'Getting paid'],
                ['value' => 'support', 'label' => 'Support'],
            ]],
            ['key' => 'one_improvement', 'type' => 'text', 'label' => 'What one thing would have made winning and delivering this quest easier?', 'optional' => true, 'max' => 500],
            ['key' => 'apply_again_nps', 'type' => 'nps', 'label' => 'How likely are you to submit proposals for similar quests on HustleSafe? (0 = not at all, 10 = extremely likely)'],
        ],
        'freelancer_rejected' => [
            ['key' => 'brief_adequacy', 'type' => 'choice', 'email_embedded' => true, 'label' => 'Did you feel the quest had enough information to write a strong proposal?', 'options' => [
                ['value' => 'yes_completely', 'label' => 'Yes, completely'],
                ['value' => 'mostly_yes', 'label' => 'Mostly yes'],
                ['value' => 'lacking', 'label' => 'It was lacking some details'],
                ['value' => 'not_enough', 'label' => 'No, it wasn\'t enough'],
            ]],
            ['key' => 'find_apply_easy', 'type' => 'ease', 'label' => 'How easy was it to find this quest and submit your proposal?'],
            ['key' => 'proposal_form_fair', 'type' => 'agree', 'label' => 'Did you feel the proposal form captured your quote and timeline fairly?'],
            ['key' => 'clarify_helpful', 'type' => 'choice', 'label' => 'Did messaging or Q&A help you before the outcome?', 'requires_clarification' => true, 'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'somewhat', 'label' => 'Somewhat'],
                ['value' => 'no', 'label' => 'No'],
            ]],
            ['key' => 'decision_timeline_clear', 'type' => 'agree', 'label' => 'While waiting, did you understand how long the client had to decide?'],
            ['key' => 'outcome_communicated', 'type' => 'clarity', 'label' => 'How clearly were you informed that you were not selected?'],
            ['key' => 'fair_chance', 'type' => 'agree', 'label' => 'Do you feel you had a fair chance to win this quest?'],
            ['key' => 'platform_fairness', 'type' => 'confidence', 'label' => 'After this outcome, how confident are you that HustleSafe treats freelancers fairly on quests like this?'],
            ['key' => 'apply_again_intent', 'type' => 'choice', 'label' => 'Would you still apply to quests in this category?', 'options' => [
                ['value' => 'likely', 'label' => 'Likely'],
                ['value' => 'maybe', 'label' => 'Maybe'],
                ['value' => 'unlikely', 'label' => 'Unlikely'],
            ]],
            ['key' => 'win_more_help', 'type' => 'text', 'label' => 'What is the single most useful thing we could add to help you win more quests?', 'optional' => true, 'max' => 300],
            ['key' => 'frustrating_parts', 'type' => 'text', 'label' => 'Did anything about the proposal process feel frustrating or confusing?', 'optional' => true, 'max' => 300],
        ],
    ],

    'option_sets' => [
        'ease' => [
            ['value' => 'very_easy', 'label' => 'Very easy'],
            ['value' => 'easy', 'label' => 'Easy'],
            ['value' => 'ok', 'label' => 'OK'],
            ['value' => 'difficult', 'label' => 'Difficult'],
            ['value' => 'very_difficult', 'label' => 'Very difficult'],
        ],
        'agree' => [
            ['value' => 'strongly_agree', 'label' => 'Strongly agree'],
            ['value' => 'agree', 'label' => 'Agree'],
            ['value' => 'neutral', 'label' => 'Neutral'],
            ['value' => 'disagree', 'label' => 'Disagree'],
            ['value' => 'strongly_disagree', 'label' => 'Strongly disagree'],
        ],
        'clarity' => [
            ['value' => 'very_clear', 'label' => 'Very clear'],
            ['value' => 'clear', 'label' => 'Clear'],
            ['value' => 'somewhat', 'label' => 'Somewhat clear'],
            ['value' => 'unclear', 'label' => 'Unclear'],
            ['value' => 'very_unclear', 'label' => 'Very unclear'],
        ],
        'fairness' => [
            ['value' => 'very_fair', 'label' => 'Very fair'],
            ['value' => 'fair', 'label' => 'Fair'],
            ['value' => 'neutral', 'label' => 'Neutral'],
            ['value' => 'unfair', 'label' => 'Unfair'],
            ['value' => 'very_unfair', 'label' => 'Very unfair'],
        ],
        'satisfaction' => [
            ['value' => 'very_satisfied', 'label' => 'Very satisfied'],
            ['value' => 'satisfied', 'label' => 'Satisfied'],
            ['value' => 'neutral', 'label' => 'Neutral'],
            ['value' => 'dissatisfied', 'label' => 'Dissatisfied'],
        ],
        'confidence' => [
            ['value' => 'very_confident', 'label' => 'Very confident'],
            ['value' => 'confident', 'label' => 'Confident'],
            ['value' => 'unsure', 'label' => 'Unsure'],
            ['value' => 'not_confident', 'label' => 'Not confident'],
        ],
    ],

    'score_maps' => [
        'proposal_quality' => [
            'very_dissatisfied' => 1,
            'dissatisfied' => 2,
            'neutral' => 3,
            'satisfied' => 4,
            'very_satisfied' => 5,
        ],
        'post_again_likelihood' => [
            'unlikely' => 1,
            'not_sure' => 2,
            'probably' => 3,
            'definitely' => 4,
        ],
        'payment_confidence' => [
            'not_confident' => 1,
            'somewhat_uncertain' => 2,
            'confident' => 3,
            'very_confident' => 4,
        ],
        'payment_release_smooth' => [
            'very_frustrating' => 1,
            'slightly_frustrating' => 2,
            'smooth' => 3,
            'very_smooth' => 4,
        ],
        'brief_adequacy' => [
            'not_enough' => 1,
            'lacking' => 2,
            'mostly_yes' => 3,
            'yes_completely' => 4,
        ],
    ],
];
