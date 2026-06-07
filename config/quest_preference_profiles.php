<?php

/**
 * Category-conditional quest preference field schemas.
 * Keys map to profile types resolved by QuestPreferenceProfileService.
 */
return [

    'profiles' => [
        'technical' => [
            'label' => 'Technical preferences',
            'hint' => 'Optional — helps freelancers align on stack, testing, and delivery.',
            'fields' => [
                'tech_stack' => ['type' => 'text', 'label' => 'Preferred tech stack', 'hint' => 'Languages, frameworks, or tools you prefer — leave blank if open.', 'max' => 200, 'placeholder' => 'E.g. React, Node.js, PostgreSQL'],
                'testing_requirements' => ['type' => 'radio', 'label' => 'Testing requirements', 'hint' => 'How much testing you expect before handoff.', 'default' => 'not_specified', 'options' => [
                    'unit_tests' => 'Unit tests required',
                    'manual' => 'Manual testing sufficient',
                    'integration' => 'Integration testing required',
                    'not_required' => 'Not required',
                    'not_specified' => 'Not specified — freelancer\'s choice',
                ]],
                'documentation_needed' => ['type' => 'checkbox_group', 'label' => 'Documentation needed', 'hint' => 'Which docs you want delivered with the work.', 'options' => [
                    'api_docs' => 'API documentation',
                    'readme' => 'README file',
                    'code_comments' => 'Code comments',
                    'user_guide' => 'User guide',
                    'setup_guide' => 'Setup/installation guide',
                    'none' => 'None',
                ]],
                'source_code_access' => ['type' => 'radio', 'label' => 'Source code access', 'hint' => 'How you want to receive the source code.', 'default' => 'not_specified', 'options' => [
                    'repo' => 'Full repository access (GitHub/GitLab)',
                    'archive' => 'Compressed file with source',
                    'deployed_only' => 'Deployed version only',
                    'not_specified' => 'Not specified — freelancer decides',
                ]],
                'revision_rounds' => ['type' => 'number', 'label' => 'Number of free revision rounds', 'hint' => 'Included rounds of changes before extra fees apply.', 'min' => 0, 'max' => 10, 'placeholder' => 'Leave blank if open'],
                'timeline_flexibility' => ['type' => 'radio', 'label' => 'Timeline flexibility', 'hint' => 'How fixed your deadline is versus quality or scope.', 'default' => 'not_specified', 'options' => [
                    'hard_deadline' => 'Hard deadline — must meet exact date',
                    'flexible_asap' => 'Flexible — ASAP preferred',
                    'extend_quality' => 'Can extend if quality improves',
                    'not_specified' => 'Not specified',
                ]],
                'deployment_hosting' => ['type' => 'radio', 'label' => 'Deployment / hosting', 'hint' => 'Who handles publishing or hosting the finished work.', 'default' => 'not_specified', 'options' => [
                    'client_hosts' => 'I\'ll handle hosting and deployment',
                    'freelancer_recommends' => 'Freelancer recommends hosting solution',
                    'client_existing' => 'I have existing hosting, freelancer configures',
                    'not_specified' => 'Not specified',
                ]],
            ],
        ],
        'physical' => [
            'label' => 'On-site & logistics preferences',
            'hint' => 'Optional — clarify materials, access, and site expectations.',
            'fields' => [
                'materials_responsibility' => ['type' => 'radio', 'label' => 'Materials responsibility', 'hint' => 'Who buys or supplies parts and materials for the job.', 'default' => 'not_specified', 'options' => [
                    'client_provides' => 'I\'ll provide all materials',
                    'freelancer_provides' => 'Freelancer provides materials (cost included in fee)',
                    'cost_sharing' => 'Cost sharing — I buy specific items, freelancer provides labor',
                    'tbd' => 'TBD during assessment',
                    'not_specified' => 'Not specified',
                ]],
                'work_location_logistics' => ['type' => 'textarea', 'label' => 'Work location & logistics', 'hint' => 'Address context, access codes, and preferred visit hours.', 'max' => 300, 'placeholder' => 'E.g. Home in Ikeja. Evenings only. Gate code required.'],
                'site_preparation' => ['type' => 'radio', 'label' => 'Site preparation status', 'hint' => 'Whether the site is ready when work starts.', 'default' => 'not_specified', 'options' => [
                    'ready' => 'Site will be ready when you arrive',
                    'needs_prep' => 'Site needs preparation (I\'ll describe)',
                    'together' => 'Will prepare together with freelancer',
                    'not_specified' => 'Not specified',
                ]],
                'assessment_required' => ['type' => 'radio', 'label' => 'Assessment required', 'hint' => 'Whether a site visit is needed before quoting or starting.', 'default' => 'not_specified', 'options' => [
                    'visit_first' => 'Please visit and assess before quoting',
                    'ready_start' => 'I\'ve measured/inspected; you can start immediately',
                    'hybrid' => 'Hybrid — visit to confirm my measurements',
                    'not_specified' => 'Not specified',
                ]],
                'weather_impact' => ['type' => 'radio', 'label' => 'Environmental / weather impact', 'hint' => 'Whether outdoor weather affects when work can happen.', 'default' => 'not_specified', 'conditional' => 'outdoor_only', 'options' => [
                    'indoor' => 'Indoor work, weather-independent',
                    'outdoor' => 'Outdoor work, weather-dependent',
                    'both' => 'Both — will assess on site',
                    'not_specified' => 'Not specified',
                ]],
                'revision_rounds' => ['type' => 'number', 'label' => 'Free revision rounds (adjustments/corrections)', 'hint' => 'Included touch-ups or corrections after the main job.', 'min' => 0, 'max' => 5, 'placeholder' => 'Leave blank if open'],
            ],
        ],
        'design' => [
            'label' => 'Creative preferences',
            'hint' => 'Optional — formats, rights, and feedback expectations.',
            'fields' => [
                'deliverable_formats' => ['type' => 'checkbox_group', 'label' => 'Deliverable format preference', 'hint' => 'File types you want to receive at the end.', 'options' => [
                    'vector' => 'Vector files (AI/EPS)',
                    'editable' => 'Editable source files (PSD/Figma/XD)',
                    'raster' => 'PNG/JPG exports',
                    'print' => 'High-res print files (300 DPI)',
                    'web' => 'Web-optimized versions',
                    'video' => 'Video files (MP4, ProRes, etc.)',
                    '3d' => '3D files (blend, obj, fbx)',
                    'other' => 'Other (describe in job description)',
                ]],
                'rights_ownership' => ['type' => 'radio', 'label' => 'Rights & ownership', 'hint' => 'Who owns the final creative work and portfolio use.', 'default' => 'not_specified', 'options' => [
                    'full_ownership' => 'I own full rights to everything',
                    'portfolio_ok' => 'Freelancer can use in portfolio',
                    'restrictions' => 'Specific restrictions apply (describe in description)',
                    'commercial_unlimited' => 'Unlimited commercial use for me',
                    'not_specified' => 'Not specified — to be discussed',
                ]],
                'revision_rounds' => ['type' => 'number', 'label' => 'Number of free revision rounds', 'hint' => 'Included design revision rounds before extra fees.', 'min' => 0, 'max' => 10, 'placeholder' => 'E.g. 3'],
                'style_reference' => ['type' => 'textarea', 'label' => 'Style / inspiration reference', 'hint' => 'Look, mood, or links that capture what you want.', 'max' => 500, 'placeholder' => 'Modern minimalist, bold colors, or reference links'],
                'brand_guidelines' => ['type' => 'textarea', 'label' => 'Brand guidelines', 'hint' => 'Colours, fonts, tone, or note if none exist yet.', 'max' => 300, 'placeholder' => 'Describe brand voice and visual identity'],
                'feedback_process' => ['type' => 'radio', 'label' => 'Feedback process', 'hint' => 'How you prefer to review and approve drafts.', 'default' => 'not_specified', 'options' => [
                    'one_round' => 'One review round, then final',
                    'multiple_rounds' => 'Multiple rounds with my detailed feedback',
                    'freelancer_decides' => 'Freelancer makes final decisions',
                    'not_specified' => 'Not specified',
                ]],
                'approval_timeline_days' => ['type' => 'number', 'label' => 'Days for your feedback per round', 'hint' => 'How quickly you will respond on each review round.', 'min' => 1, 'max' => 30, 'placeholder' => 'E.g. 3'],
            ],
        ],
        'professional' => [
            'label' => 'Service delivery preferences',
            'hint' => 'Optional — deliverables, when you are easiest to reach on HustleSafe chat, and revisions. All project communication stays in-app for escrow protection.',
            'fields' => [
                'deliverable_formats' => ['type' => 'checkbox_group', 'label' => 'Deliverable format', 'hint' => 'How you want the final output delivered.', 'options' => [
                    'report' => 'Written report (Word/PDF)',
                    'spreadsheet' => 'Spreadsheet (Excel/Google Sheets)',
                    'presentation' => 'Presentation (PowerPoint/Google Slides)',
                    'live_consult' => 'Live consultation/discussion',
                    'recorded' => 'Recorded session/video walkthrough',
                    'dashboard' => 'Dashboard or data tool',
                    'raw_data' => 'Raw data files',
                    'other' => 'Other (describe in job description)',
                ]],
                'best_contact_time' => ['type' => 'radio', 'label' => 'Best time to reach you (in-app chat)', 'hint' => 'When you usually reply on HustleSafe messaging — not phone or email.', 'default' => 'not_specified', 'options' => [
                    'morning' => 'Morning (before noon)',
                    'afternoon' => 'Afternoon (noon–5pm)',
                    'evening' => 'Evening (after 5pm)',
                    'flexible' => 'Flexible — any time',
                    'not_specified' => 'Not specified',
                ]],
                'revision_rounds' => ['type' => 'number', 'label' => 'Number of free revision rounds', 'hint' => 'Included revision rounds before extra fees apply.', 'min' => 0, 'max' => 10, 'placeholder' => 'Leave blank if open'],
                'feedback_timeline_days' => ['type' => 'number', 'label' => 'Your feedback turnaround per round (days)', 'hint' => 'Business days you need to reply on each draft.', 'min' => 1, 'max' => 14, 'placeholder' => 'E.g. 3 business days'],
                'confidentiality_nda' => ['type' => 'radio', 'label' => 'Confidentiality / NDA', 'hint' => 'Whether formal or informal confidentiality is required.', 'default' => 'not_specified', 'conditional' => 'sensitive_only', 'options' => [
                    'nda_required' => 'NDA required',
                    'confidential_informal' => 'Confidentiality expected but no formal NDA',
                    'none' => 'No confidentiality requirement',
                    'not_specified' => 'Not specified',
                ]],
                'data_handling' => ['type' => 'text', 'label' => 'Data handling requirements', 'hint' => 'How sensitive data must be stored, shared, or deleted.', 'max' => 200, 'conditional' => 'data_handling', 'placeholder' => 'E.g. Delete files after 30 days'],
            ],
        ],
    ],

    'detection' => [
        'technical' => [
            'parents' => ['technology-software', 'gaming-interactive'],
            'leaves' => ['store-setup', 'api-integration', 'ml-ai', 'data-engineering', 'qa-automation', 'devops-cloud', 'unity-unreal-support'],
            'keywords' => ['development', 'software', 'programming', 'app', 'api', 'system', 'cyber', 'code', 'automation', 'cms'],
        ],
        'physical' => [
            'parents' => ['trades-field', 'real-estate', 'media-events', 'agriculture-supply'],
            'leaves' => [
                'electrical-install', 'plumbing-hvac', 'carpentry-interiors', 'generator-power', 'facility-maintenance',
                'photography', 'videography-livestream', 'event-planning', 'estate-management',
                'events-mc', 'events-dj', 'auto-mechanic', 'household-electrician', 'welding-fabrication',
                'bricklaying-masonry', 'tiling-flooring', 'landscaping-gardening', 'painting-decorating',
            ],
            'keywords' => ['install', 'repair', 'maintenance', 'on-site', 'field', 'mechanic', 'electrician', 'welding', 'brick', 'tiling', 'landscap', 'painting', 'plumb', 'carpent', 'generator', 'hvac'],
        ],
        'design' => [
            'parents' => ['design-creative'],
            'leaves' => ['motion-video', '3d-visualization', 'game-design-economy', 'architecture-viz'],
            'keywords' => ['design', 'logo', 'brand', 'ui', 'ux', 'illustration', 'animation', 'video edit', 'creative', 'visual'],
        ],
        'professional' => [
            'parents' => [
                'business-operations', 'finance-accounting', 'legal-compliance', 'writing-content',
                'marketing-growth', 'education-training', 'healthcare-wellness', 'sales-bd',
                'ecommerce-retail', 'nonprofit-community', 'research-decision', 'engineering-stem',
            ],
            'leaves' => [
                'virtual-assistant', 'project-management', 'tax-advisory', 'contracts', 'copywriting',
                'social-media', 'grant-writing', 'survey-enumerator', 'bookkeeping', 'customer-support',
            ],
            'keywords' => ['consult', 'advisory', 'virtual assistant', 'writing', 'research', 'analysis', 'accounting', 'legal', 'management', 'support'],
        ],
    ],

    'sensitive_confidentiality_parents' => ['legal-compliance', 'finance-accounting'],
    'data_handling_parents' => ['finance-accounting', 'legal-compliance', 'healthcare-wellness', 'research-decision'],
    'outdoor_weather_leaves' => ['photography', 'videography-livestream', 'landscaping-gardening', 'painting-decorating', 'event-planning', 'events-mc', 'events-dj'],

];
