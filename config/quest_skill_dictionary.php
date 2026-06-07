<?php

/**
 * Suggested skills for quest required_skills, keyed by parent category slug.
 * Leaf-specific lists override/extend parent lists via by_leaf.
 */
return [

    'common' => [
        'Communication',
        'Project management',
        'Time management',
        'Problem solving',
        'Attention to detail',
        'Client reporting',
    ],

    'by_parent' => [
        'technology-software' => [
            'JavaScript', 'TypeScript', 'Python', 'PHP', 'Java', 'C#', 'Go', 'Rust',
            'React', 'Vue.js', 'Angular', 'Next.js', 'Node.js', 'Laravel', 'Django',
            'PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'REST API', 'GraphQL',
            'Docker', 'Kubernetes', 'AWS', 'Azure', 'GCP', 'CI/CD', 'Git',
            'Unit testing', 'Integration testing', 'TDD', 'Agile', 'Scrum',
            'UI integration', 'Responsive design', 'Web accessibility',
        ],
        'design-creative' => [
            'Figma', 'Adobe Photoshop', 'Adobe Illustrator', 'Adobe InDesign',
            'UI design', 'UX design', 'Wireframing', 'Prototyping', 'Design systems',
            'Brand identity', 'Logo design', 'Typography', 'Color theory',
            'Motion design', 'After Effects', 'Premiere Pro', '3D modeling', 'Blender',
            'Print design', 'Packaging design', 'Social media creatives',
        ],
        'writing-content' => [
            'Copywriting', 'SEO writing', 'Blog writing', 'Technical writing',
            'Proofreading', 'Editing', 'Content strategy', 'Storytelling',
            'Email copy', 'Landing page copy', 'Product descriptions',
            'Translation', 'Localization', 'Research', 'Ghostwriting',
        ],
        'marketing-growth' => [
            'Meta Ads', 'Google Ads', 'TikTok Ads', 'LinkedIn Ads',
            'SEO', 'SEM', 'Google Analytics', 'GA4', 'Tag Manager',
            'Email marketing', 'Klaviyo', 'Mailchimp', 'HubSpot',
            'Social media strategy', 'Content calendar', 'Influencer marketing',
            'CRO', 'A/B testing', 'Funnel optimization', 'Market research',
        ],
        'business-operations' => [
            'Virtual assistance', 'Calendar management', 'Data entry',
            'Microsoft Excel', 'Google Sheets', 'Notion', 'Asana', 'Trello',
            'Customer support', 'CRM', 'Process documentation', 'SOP writing',
            'Project coordination', 'Vendor management', 'Reporting',
        ],
        'finance-accounting' => [
            'Bookkeeping', 'QuickBooks', 'Xero', 'Sage', 'Payroll',
            'Tax preparation', 'Financial reporting', 'Budgeting',
            'Excel modeling', 'Accounts payable', 'Accounts receivable',
            'Reconciliation', 'Audit support', 'VAT compliance',
        ],
        'legal-compliance' => [
            'Contract drafting', 'Contract review', 'Legal research',
            'Compliance', 'NDA', 'Terms of service', 'Privacy policy',
            'Corporate law', 'IP law', 'Regulatory filings',
        ],
        'engineering-stem' => [
            'AutoCAD', 'Revit', 'SolidWorks', 'MATLAB', 'GIS',
            'Structural analysis', 'Electrical design', 'Mechanical drafting',
            'Surveying', 'Environmental assessment', 'Technical drawings',
            'BIM', 'Quantity surveying', 'Site supervision',
        ],
        'trades-field' => [
            'Electrical wiring', 'Plumbing', 'Pipe fitting', 'HVAC',
            'Carpentry', 'Masonry', 'Tiling', 'Painting', 'Welding',
            'Generator repair', 'Solar installation', 'Air conditioning',
            'Fault finding', 'Safety compliance', 'Site cleanup',
            'Appliance repair', 'Roofing', 'Flooring', 'Landscaping',
        ],
        'education-training' => [
            'Mathematics tutoring', 'Physics tutoring', 'Chemistry tutoring',
            'English tutoring', 'IELTS prep', 'WAEC prep', 'JAMB prep',
            'Curriculum design', 'Lesson planning', 'Online teaching',
            'Corporate training', 'Presentation skills', 'Assessment design',
        ],
        'healthcare-wellness' => [
            'Medical transcription', 'Patient scheduling', 'Health records',
            'Telehealth support', 'Nutrition coaching', 'Fitness coaching',
            'Mental health first aid', 'Care coordination', 'HIPAA awareness',
        ],
        'media-events' => [
            'Photography', 'Videography', 'Video editing', 'Live streaming',
            'Podcast editing', 'Sound mixing', 'Event planning', 'MC hosting',
            'DJ', 'Stage management', 'Lighting', 'Public relations',
            'Portrait photography', 'Product photography', 'Drone footage',
        ],
        'sales-bd' => [
            'Lead generation', 'Cold calling', 'Cold email', 'LinkedIn outreach',
            'CRM', 'Salesforce', 'Pipeline management', 'Negotiation',
            'Proposal writing', 'Account management', 'B2B sales', 'B2C sales',
        ],
        'ecommerce-retail' => [
            'Shopify', 'WooCommerce', 'Product listing', 'Inventory management',
            'Order fulfillment', 'Customer service', 'Marketplace ops',
            'Amazon seller', 'Jumia seller', 'Product photography',
            'Pricing strategy', 'Catalog management',
        ],
        'gaming-interactive' => [
            'Unity', 'Unreal Engine', 'Game design', 'Level design',
            '2D art', '3D modeling', 'Character design', 'Narrative design',
            'Mobile game dev', 'Multiplayer networking', 'Game QA',
        ],
        'agriculture-supply' => [
            'Farm advisory', 'Crop management', 'Livestock care',
            'Agribusiness planning', 'Supply chain', 'Cold chain logistics',
            'Irrigation', 'Soil testing', 'Harvest planning',
        ],
        'real-estate' => [
            'Property management', 'Tenant relations', 'Lease administration',
            'Facility inspection', 'Estate coordination', 'Listing preparation',
            'Market analysis', 'Property photography',
        ],
    ],

    'by_leaf' => [
        'web-frontend' => ['React', 'Vue.js', 'Angular', 'Next.js', 'Tailwind CSS', 'HTML', 'CSS', 'SASS'],
        'web-backend' => ['Laravel', 'Node.js', 'Express', 'Django', 'FastAPI', 'Spring Boot', 'API design'],
        'fullstack-development' => ['React', 'Laravel', 'Node.js', 'PostgreSQL', 'Full-stack architecture'],
        'mobile-apps' => ['React Native', 'Flutter', 'Swift', 'Kotlin', 'iOS', 'Android'],
        'devops-cloud' => ['Docker', 'Kubernetes', 'Terraform', 'AWS', 'CI/CD', 'Linux administration'],
        'qa-automation' => ['Selenium', 'Cypress', 'Playwright', 'Test automation', 'Manual QA'],
        'cybersecurity' => ['Penetration testing', 'Security audit', 'OWASP', 'Network security'],
        'data-engineering' => ['ETL', 'Apache Spark', 'Airflow', 'dbt', 'Data pipelines'],
        'ml-ai' => ['Machine learning', 'TensorFlow', 'PyTorch', 'LLM integration', 'Data science'],
        'ui-ux-design' => ['Figma', 'User research', 'Usability testing', 'Design systems'],
        'plumbing-hvac' => ['Pipe installation', 'Drainage', 'Water heater', 'AC servicing'],
        'electrical-install' => ['Wiring', 'Circuit breaker', 'Inverter installation', 'Earthing'],
        'household-electrician' => ['Socket repair', 'Lighting install', 'Fault diagnosis'],
        'photography' => ['Portrait photography', 'Event photography', 'Lightroom', 'Studio lighting'],
    ],

];
