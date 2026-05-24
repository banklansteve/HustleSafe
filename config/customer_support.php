<?php

return [
    'categories' => [
        'quest_issues' => ['label' => 'Quest Issues', 'priority' => 'medium'],
        'proposal_payments' => ['label' => 'Proposal & Payments', 'priority' => 'high'],
        'account_verification' => ['label' => 'Account & Verification', 'priority' => 'medium'],
        'dispute_support' => ['label' => 'Dispute Support', 'priority' => 'urgent'],
        'general_enquiry' => ['label' => 'General Enquiry', 'priority' => 'medium'],
    ],

    'message_reactions' => ['👍', '❤️', '😂', '😮', '🙏', '🎉'],

    'online_window_minutes' => 5,
    'history_retention_days' => 30,
    'poll_interval_visible_ms' => (int) env('SUPPORT_CHAT_POLL_VISIBLE_MS', 300),
    'poll_interval_hidden_ms' => (int) env('SUPPORT_CHAT_POLL_HIDDEN_MS', 1500),
    'inactivity_close_minutes' => 30,
    'rating_email_delay_minutes' => 30,
    'send_rating_email_on_close' => true,
    'max_attachments' => 5,
    'max_attachment_kb' => 10240,

    'session_closed_customer_body' => 'This support session has ended. You can start a new chat anytime if you need more help. We would love your quick feedback on today\'s experience.',
    'session_closed_admin_body' => 'You ended this live support session. The customer has been prompted for feedback.',

    'closure_reactions' => [
        ['key' => 'very_unhappy', 'emoji' => '😢', 'label' => 'Very unhappy'],
        ['key' => 'unhappy', 'emoji' => '😕', 'label' => 'Unhappy'],
        ['key' => 'neutral', 'emoji' => '😐', 'label' => 'Okay'],
        ['key' => 'happy', 'emoji' => '🙂', 'label' => 'Happy'],
        ['key' => 'very_happy', 'emoji' => '😄', 'label' => 'Delighted'],
    ],

    'message_templates' => [
        'opening' => [
            [
                'id' => 'welcome',
                'label' => 'Welcome',
                'body' => "Hi {{customer_name}},\n\nI'm {{agent_signature}}, and I'll be helping you with your support request today.\n\nHow can I assist you?",
            ],
            [
                'id' => 'thanks_waiting',
                'label' => 'Thanks for waiting',
                'body' => "Hi {{customer_name}}, thank you for waiting.\n\nI'm {{agent_signature}}, and I've picked up your chat. How can I help you today?",
            ],
            [
                'id' => 'reviewing',
                'label' => 'Reviewing details',
                'body' => "Hi {{customer_name}}, I'm {{agent_signature}}. I'm reviewing the details of your request now — please share anything else that might help while I look into this.",
            ],
        ],
        'closing' => [
            [
                'id' => 'close_standard',
                'label' => 'Friendly close',
                'body' => "Hi {{customer_name}}, I hope we've covered everything you needed today.\n\nIs there anything else I can help you with before we wrap up?\n\nIf you need more help later, you're always welcome to start a new live chat here anytime.\n\nWe'll send you a short feedback survey shortly — your input really helps us improve. Thank you for chatting with {{agent_signature}} today!",
                'ends_session' => true,
            ],
            [
                'id' => 'close_resolved',
                'label' => 'Issue resolved',
                'body' => "Hi {{customer_name}}, I'm glad we could get this sorted for you.\n\nIs there anything else you'd like help with before we close this session?\n\nYou can open a new live chat anytime if something else comes up. A quick feedback form will arrive shortly — we'd love to hear how we did.\n\nThank you, and take care!\n— {{agent_signature}}",
                'ends_session' => true,
            ],
            [
                'id' => 'close_brief',
                'label' => 'Quick close',
                'body' => "Hi {{customer_name}}, is there anything else I can help with before we close?\n\nYou're welcome to start a new chat anytime if you need us again. We'll share a quick feedback survey shortly.\n\nThank you!\n— {{agent_signature}}",
                'ends_session' => true,
            ],
        ],
    ],

    'feedback_survey' => [
        [
            'id' => 'issue_resolved',
            'question' => 'Did we resolve your issue today?',
            'options' => [
                ['value' => 'yes', 'label' => 'Yes, completely'],
                ['value' => 'partial', 'label' => 'Partly — still need help'],
                ['value' => 'no', 'label' => 'Not yet'],
            ],
        ],
        [
            'id' => 'response_quality',
            'question' => 'How clear and helpful were our replies?',
            'options' => [
                ['value' => 'excellent', 'label' => 'Excellent'],
                ['value' => 'good', 'label' => 'Good'],
                ['value' => 'fair', 'label' => 'Fair'],
                ['value' => 'poor', 'label' => 'Needs improvement'],
            ],
        ],
    ],
];
