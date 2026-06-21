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
                'testing_requirements' => ['type' => 'radio', 'label' => 'What level of testing do you expect before handoff?', 'hint' => 'How much testing you expect before handoff.', 'default' => 'not_specified', 'options' => [
                    'unit_tests' => 'Unit tests required',
                    'manual' => 'Manual testing sufficient',
                    'integration' => 'Integration testing required',
                    'not_required' => 'Not required',
                    'not_specified' => 'Not specified — freelancer\'s choice',
                ]],
                'documentation_needed' => ['type' => 'checkbox_group', 'label' => 'What documentation should be delivered with the work?', 'hint' => 'Which docs you want delivered with the work.', 'options' => [
                    'api_docs' => 'API documentation',
                    'readme' => 'README file',
                    'code_comments' => 'Code comments',
                    'user_guide' => 'User guide',
                    'setup_guide' => 'Setup/installation guide',
                    'none' => 'None',
                ]],
                'source_code_access' => ['type' => 'radio', 'label' => 'How should you receive the source code?', 'hint' => 'How you want to receive the source code.', 'default' => 'not_specified', 'options' => [
                    'repo' => 'Full repository access (GitHub/GitLab)',
                    'archive' => 'Compressed file with source',
                    'deployed_only' => 'Deployed version only',
                    'not_specified' => 'Not specified — freelancer decides',
                ]],
                'revision_rounds' => ['type' => 'number', 'label' => 'Number of free revision rounds', 'hint' => 'Included rounds of changes before extra fees apply.', 'min' => 0, 'max' => 10, 'placeholder' => 'Leave blank if open'],
                'timeline_flexibility' => ['type' => 'radio', 'label' => 'How fixed is your deadline versus quality or scope?', 'hint' => 'How fixed your deadline is versus quality or scope.', 'default' => 'not_specified', 'options' => [
                    'hard_deadline' => 'Hard deadline — must meet exact date',
                    'flexible_asap' => 'Flexible — ASAP preferred',
                    'extend_quality' => 'Can extend if quality improves',
                    'not_specified' => 'Not specified',
                ]],
                'deployment_hosting' => ['type' => 'radio', 'label' => 'Who handles deployment and hosting?', 'hint' => 'Who handles publishing or hosting the finished work.', 'default' => 'not_specified', 'options' => [
                    'client_hosts' => 'I\'ll handle hosting and deployment',
                    'freelancer_recommends' => 'Freelancer recommends hosting solution',
                    'client_existing' => 'I have existing hosting, freelancer configures',
                    'not_specified' => 'Not specified',
                ]],
            ],
        ],
        'physical' => [
            'label' => 'On-site & logistics preferences',
            'hint' => 'Optional — clarify materials, access, and site expectations. Share exact addresses only with your awarded freelancer in-app after acceptance.',
            'fields' => [
                'service_location' => ['type' => 'radio', 'label' => 'Where should the service happen?', 'hint' => 'General location type only — city/LGA/state on the quest is enough for matching. Exact address stays private until you award a proposal.', 'default' => 'not_specified', 'options' => [
                    'client_place' => "At my place (the client's location)",
                    'provider_place' => "At the provider's workshop / premises",
                    'remote' => 'Remote / off-site',
                    'flexible' => 'Flexible — to be agreed',
                    'not_specified' => 'Not specified',
                ]],
                'materials_responsibility' => ['type' => 'radio', 'label' => 'Who provides materials and parts for the job?', 'hint' => 'Who buys or supplies parts and materials for the job.', 'default' => 'not_specified', 'options' => [
                    'client_provides' => 'I\'ll provide all materials',
                    'freelancer_provides' => 'Freelancer provides materials (cost included in fee)',
                    'cost_sharing' => 'Cost sharing — I buy specific items, freelancer provides labor',
                    'tbd' => 'TBD during assessment',
                    'not_specified' => 'Not specified',
                ]],
                'site_preparation' => ['type' => 'radio', 'label' => 'Will the site be ready when work starts?', 'hint' => 'Whether the site is ready when work starts.', 'default' => 'not_specified', 'options' => [
                    'ready' => 'Site will be ready when you arrive',
                    'needs_prep' => 'Site needs preparation (I\'ll describe)',
                    'together' => 'Will prepare together with freelancer',
                    'not_specified' => 'Not specified',
                ]],
                'assessment_required' => ['type' => 'radio', 'label' => 'Do you need a site visit before work starts?', 'hint' => 'Whether a site visit is needed before quoting or starting.', 'default' => 'not_specified', 'options' => [
                    'visit_first' => 'Please visit and assess before quoting',
                    'ready_start' => 'I\'ve measured/inspected; you can start immediately',
                    'hybrid' => 'Hybrid — visit to confirm my measurements',
                    'not_specified' => 'Not specified',
                ]],
                'weather_impact' => ['type' => 'radio', 'label' => 'Does weather affect when work can happen?', 'hint' => 'Whether outdoor weather affects when work can happen.', 'default' => 'not_specified', 'conditional' => 'outdoor_only', 'options' => [
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
                'deliverable_formats' => ['type' => 'checkbox_group', 'label' => 'What file formats do you want delivered?', 'hint' => 'File types you want to receive at the end.', 'options' => [
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
                'feedback_process' => ['type' => 'radio', 'label' => 'How do you prefer to review and approve drafts?', 'hint' => 'How you prefer to review and approve drafts.', 'default' => 'not_specified', 'options' => [
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
            'hint' => 'Optional — deliverables and revisions. All project communication stays in-app for escrow protection.',
            'fields' => [
                'deliverable_formats' => ['type' => 'checkbox_group', 'label' => 'What deliverable formats do you need?', 'hint' => 'How you want the final output delivered.', 'options' => [
                    'report' => 'Written report (Word/PDF)',
                    'spreadsheet' => 'Spreadsheet (Excel/Google Sheets)',
                    'presentation' => 'Presentation (PowerPoint/Google Slides)',
                    'live_consult' => 'Live consultation/discussion',
                    'recorded' => 'Recorded session/video walkthrough',
                    'dashboard' => 'Dashboard or data tool',
                    'raw_data' => 'Raw data files',
                    'other' => 'Other (describe in job description)',
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
        'lessons' => [
            'label' => 'Lesson & coaching preferences',
            'hint' => 'Optional — how, where, and how often you want sessions to run.',
            'fields' => [
                'delivery_mode' => ['type' => 'radio', 'label' => 'How should sessions hold?', 'hint' => 'Where you want the lessons to take place.', 'default' => 'not_specified', 'options' => [
                    'my_location' => 'At my home / office',
                    'instructor_location' => "At the instructor's place / studio",
                    'online' => 'Online (video call)',
                    'hybrid' => 'Hybrid — in-person and online',
                    'not_specified' => 'Not specified',
                ]],
                'session_frequency' => ['type' => 'radio', 'label' => 'How often should sessions run?', 'hint' => 'How often sessions should run.', 'default' => 'not_specified', 'options' => [
                    'one_off' => 'One-off session',
                    'daily' => 'Daily',
                    'weekly' => 'Several times a week',
                    'monthly' => 'Several times a month',
                    'intensive' => 'Intensive (several sessions over a short period)',
                    'flexible' => 'Flexible — to be agreed',
                    'not_specified' => 'Not specified',
                ]],
                'sessions_per_week' => ['type' => 'number', 'label' => 'How many times per week?', 'hint' => 'E.g. 3 for three sessions each week.', 'min' => 1, 'max' => 7, 'placeholder' => 'E.g. 3', 'show_when' => ['field' => 'session_frequency', 'value' => 'weekly']],
                'sessions_per_month' => ['type' => 'number', 'label' => 'How many times per month?', 'hint' => 'E.g. 5 for five sessions each month.', 'min' => 1, 'max' => 31, 'placeholder' => 'E.g. 5', 'show_when' => ['field' => 'session_frequency', 'value' => 'monthly']],
                'participant_level' => ['type' => 'radio', 'label' => 'What is the learner\'s starting level?', 'hint' => 'Starting point of the learner(s).', 'default' => 'not_specified', 'options' => [
                    'beginner' => 'Complete beginner',
                    'intermediate' => 'Intermediate',
                    'advanced' => 'Advanced',
                    'mixed' => 'Mixed group',
                    'not_specified' => 'Not specified',
                ]],
                'num_participants' => ['type' => 'number', 'label' => 'Number of learners', 'hint' => 'How many people will attend.', 'min' => 1, 'max' => 50, 'placeholder' => 'E.g. 1'],
                'session_length_minutes' => ['type' => 'number', 'label' => 'Preferred session length (minutes)', 'hint' => 'Typical length of each session.', 'min' => 15, 'max' => 480, 'placeholder' => 'E.g. 60'],
                'equipment_materials' => ['type' => 'radio', 'label' => 'Who provides equipment and learning materials?', 'hint' => 'Who provides instruments, equipment, or learning materials.', 'default' => 'not_specified', 'options' => [
                    'client_provides' => 'I provide equipment / materials',
                    'instructor_provides' => 'Instructor brings equipment / materials',
                    'shared' => 'Shared — we each bring some',
                    'not_specified' => 'Not specified',
                ]],
            ],
        ],
        'care' => [
            'label' => 'Care & personal-service preferences',
            'hint' => 'Optional — schedule and special requirements. Exact home addresses are shared in-app only after you award a proposal.',
            'fields' => [
                'service_location' => ['type' => 'radio', 'label' => 'Where should the service happen?', 'hint' => 'General location type only — city/LGA/state on the quest is enough for matching.', 'default' => 'not_specified', 'options' => [
                    'client_place' => "At my home (the client's location)",
                    'provider_place' => "At the provider's place / salon / clinic",
                    'remote' => 'Remote / virtual',
                    'flexible' => 'Flexible — to be agreed',
                    'not_specified' => 'Not specified',
                ]],
                'schedule_pattern' => ['type' => 'radio', 'label' => 'How often do you need this service?', 'hint' => 'How often the service is needed — one-off, recurring, or live-in.', 'default' => 'not_specified', 'options' => [
                    'one_off' => 'One-off',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly / recurring',
                    'live_in' => 'Live-in / full-time',
                    'flexible' => 'Flexible',
                    'not_specified' => 'Not specified',
                ]],
                'supplies_responsibility' => ['type' => 'radio', 'label' => 'Who should provide supplies and products?', 'hint' => 'Who provides products, tools, or supplies for the service.', 'default' => 'not_specified', 'options' => [
                    'client_provides' => 'I provide supplies / products',
                    'provider_provides' => 'Provider brings supplies / products',
                    'shared' => 'Shared',
                    'not_specified' => 'Not specified',
                ]],
                'experience_preference' => ['type' => 'radio', 'label' => 'What level of training or experience do you prefer?', 'hint' => 'Level of training or certification you expect from the provider.', 'default' => 'not_specified', 'options' => [
                    'certified' => 'Certified / professionally trained',
                    'experienced' => 'Experienced (certification not required)',
                    'open' => 'Open to all experience levels',
                    'not_specified' => 'Not specified',
                ]],
                'special_requirements' => ['type' => 'textarea', 'label' => 'Any special requirements or care notes?', 'hint' => 'Allergies, routines, languages, or other care notes — no street addresses or contact details.', 'max' => 400, 'placeholder' => 'E.g. Toddler with a nut allergy; gentle approach; afternoons preferred.'],
            ],
        ],
        'logistics' => [
            'label' => 'Logistics & delivery preferences',
            'hint' => 'Optional — what is moving and the vehicle or help needed. Pickup/drop-off street addresses are shared in-app only after award.',
            'fields' => [
                'item_description' => ['type' => 'text', 'label' => 'What is being moved / delivered?', 'hint' => 'Brief description of the goods or items.', 'max' => 200, 'placeholder' => 'E.g. 3-bedroom flat contents; documents; fragile glassware'],
                'timing_notes' => ['type' => 'textarea', 'label' => 'Pickup & drop-off timing', 'hint' => 'Preferred dates, time windows, and floor/lift access — no street addresses on the public quest.', 'max' => 300, 'placeholder' => 'E.g. Weekday mornings preferred. Third floor, no lift at pickup.'],
                'route_type' => ['type' => 'radio', 'label' => 'What distance does this delivery cover?', 'hint' => 'Distance / coverage for this job.', 'default' => 'not_specified', 'options' => [
                    'within_city' => 'Within the same city',
                    'interstate' => 'Interstate / intercity',
                    'not_specified' => 'Not specified',
                ]],
                'vehicle_size' => ['type' => 'radio', 'label' => 'What size of vehicle do you need?', 'hint' => 'Size of vehicle the job likely requires.', 'default' => 'not_specified', 'options' => [
                    'bike' => 'Dispatch bike',
                    'car' => 'Car',
                    'van' => 'Van',
                    'truck' => 'Truck',
                    'not_specified' => 'Not specified — provider advises',
                ]],
                'handling_care' => ['type' => 'radio', 'label' => 'How fragile or heavy are the items?', 'hint' => 'How delicate or heavy the items are.', 'default' => 'not_specified', 'options' => [
                    'standard' => 'Standard',
                    'fragile' => 'Fragile — extra care needed',
                    'heavy_bulky' => 'Heavy / bulky',
                    'not_specified' => 'Not specified',
                ]],
                'labour_help' => ['type' => 'radio', 'label' => 'How much loading and offloading help do you need?', 'hint' => 'Whether you need help loading and offloading.', 'default' => 'not_specified', 'options' => [
                    'driver_only' => 'Driver only',
                    'driver_plus_one' => 'Driver + 1 helper',
                    'full_crew' => 'Full crew',
                    'not_specified' => 'Not specified',
                ]],
            ],
        ],
    ],

    'detection' => [
        'technical' => [
            'parents' => ['technology-software', 'gaming-interactive'],
            'leaves' => ['store-setup', 'api-integration', 'ml-ai', 'data-engineering', 'qa-automation', 'devops-cloud', 'unity-unreal-support'],
            // NB: keyword matching is substring-based (intentional stems like "landscap"/"plumb"
            // elsewhere). Avoid short fragments such as "api" that collide with unrelated words
            // (e.g. "landsc-api-ng"); the api-integration leaf already covers API work.
            'keywords' => ['development', 'software', 'programming', 'system', 'cyber', 'automation', 'cms'],
        ],
        'physical' => [
            'parents' => [
                'trades-field', 'real-estate', 'media-events', 'agriculture-supply',
                'home-office-cleaning', 'repairs-maintenance', 'automotive-services',
                'installation-assembly', 'laundry-textile-care', 'catering-food-services',
                'specialized-artisan',
            ],
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
        'lessons' => [
            'parents' => [],
            'leaves' => [
                'music-lessons', 'dance-classes', 'sports-coaching', 'driving-lessons',
                'computer-it-training', 'trade-skills-workshops', 'tutoring-stem', 'tutoring-languages',
                'exam-prep', 'fitness-coaching', 'yoga-pilates',
            ],
            'keywords' => [],
        ],
        'care' => [
            'parents' => ['childcare-eldercare', 'beauty-personal-care'],
            'leaves' => [],
            'keywords' => [],
        ],
        'logistics' => [
            'parents' => ['transportation-logistics'],
            'leaves' => [],
            'keywords' => [],
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
    'outdoor_weather_leaves' => [
        'photography', 'videography-livestream', 'landscaping-gardening', 'painting-decorating',
        'event-planning', 'events-mc', 'events-dj', 'lawn-maintenance', 'tree-trimming-removal',
        'garden-design', 'window-facade-cleaning', 'roofing-repair', 'car-wash-detailing',
    ],

];
