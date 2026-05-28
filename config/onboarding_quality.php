<?php

return [
    'review_window_hours' => 48,

    'generic_bio_phrases' => [
        'lorem ipsum',
        'professional freelancer',
        'hard worker',
        'team player',
        'go-getter',
        'detail oriented',
        'to be updated',
        'coming soon',
        'n/a',
        'test bio',
    ],

    'stock_photo_url_patterns' => [
        'placeholder',
        'via.placeholder',
        'unsplash.com',
        'pexels.com',
        'placehold.co',
        'dummyimage',
        'ui-avatars.com',
        'gravatar.com/avatar',
    ],

    'nudge_templates' => [
        'photo' => [
            ['key' => 'photo_real_face', 'label' => 'Use a real photo', 'subject' => 'Update your profile photo', 'body' => 'Please upload a clear photo of yourself (not a logo, stock image, or AI-generated portrait). Clients and freelancers trust profiles with authentic photos.'],
            ['key' => 'photo_quality', 'label' => 'Improve photo quality', 'subject' => 'Profile photo needs a clearer shot', 'body' => 'Your profile photo is hard to verify. Upload a well-lit, front-facing photo without heavy filters or watermarks.'],
        ],
        'bio' => [
            ['key' => 'bio_descriptive', 'label' => 'Write a descriptive bio', 'subject' => 'Strengthen your bio', 'body' => 'Your bio looks generic or too short. Add what you do, who you help, and what makes your experience credible (2–4 sentences minimum).'],
            ['key' => 'bio_specific', 'label' => 'Be more specific', 'subject' => 'Make your bio more specific', 'body' => 'Replace placeholder wording with concrete skills, industries, and outcomes you deliver on HustleSafe.'],
        ],
        'skills' => [
            ['key' => 'skills_align', 'label' => 'Align skills & experience', 'subject' => 'Align your skills with your experience', 'body' => 'Your headline, profession, and work categories do not line up. Update them so clients can see you are qualified for the quests you target.'],
            ['key' => 'skills_add', 'label' => 'Add missing skills context', 'subject' => 'Complete your professional details', 'body' => 'Add profession, years of experience, and categories that match the work you want to win.'],
        ],
        'portfolio' => [
            ['key' => 'portfolio_add', 'label' => 'Add portfolio work', 'subject' => 'Add portfolio samples', 'body' => 'Freelancers with authentic portfolio samples convert better. Add 2–3 real projects with context (your role, outcome, and tools used).'],
            ['key' => 'portfolio_authentic', 'label' => 'Portfolio authenticity', 'subject' => 'Clarify portfolio ownership', 'body' => 'Some portfolio items need clearer attribution. Only upload work you personally delivered and can verify if asked.'],
        ],
        'location' => [
            ['key' => 'location_complete', 'label' => 'Complete location', 'subject' => 'Add your location details', 'body' => 'Please add your address, state, LGA, and city. Location helps matching, escrow checks, and trust signals on HustleSafe.'],
        ],
        'categories' => [
            ['key' => 'categories_pick', 'label' => 'Choose work categories', 'subject' => 'Select your work subcategories', 'body' => 'Pick at least one leaf subcategory under Account → Work categories so we can match you to relevant quests.'],
        ],
        'general' => [
            ['key' => 'general_welcome', 'label' => 'General onboarding polish', 'subject' => 'Polish your HustleSafe profile', 'body' => 'Your profile is almost ready. Review your photo, bio, location, and professional details so you can post and apply with confidence.'],
        ],
    ],
];
