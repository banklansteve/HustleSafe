<?php

/**
 * Parent categories + subcategories for quests & freelancer preferences.
 * Extend by appending to this array — QuestCategorySeeder replants from scratch in dev.
 */
return [
    ['name' => 'Technology & Software', 'slug' => 'technology-software', 'children' => [
        ['name' => 'Web development (front-end)', 'slug' => 'web-frontend'],
        ['name' => 'Web development (back-end)', 'slug' => 'web-backend'],
        ['name' => 'Full-stack development', 'slug' => 'fullstack-development'],
        ['name' => 'Mobile apps (iOS / Android)', 'slug' => 'mobile-apps'],
        ['name' => 'DevOps & cloud (AWS, Azure, GCP)', 'slug' => 'devops-cloud'],
        ['name' => 'QA & test automation', 'slug' => 'qa-automation'],
        ['name' => 'Cybersecurity & audits', 'slug' => 'cybersecurity'],
        ['name' => 'Data engineering & ETL', 'slug' => 'data-engineering'],
        ['name' => 'Machine learning & AI', 'slug' => 'ml-ai'],
        ['name' => 'API & systems integration', 'slug' => 'api-integration'],
    ]],
    ['name' => 'Design & Creative', 'slug' => 'design-creative', 'children' => [
        ['name' => 'UI / UX design', 'slug' => 'ui-ux-design'],
        ['name' => 'Brand & identity', 'slug' => 'brand-identity'],
        ['name' => 'Graphic design & illustration', 'slug' => 'graphic-illustration'],
        ['name' => 'Motion & video editing', 'slug' => 'motion-video'],
        ['name' => '3D & product visualization', 'slug' => '3d-visualization'],
        ['name' => 'Packaging & print design', 'slug' => 'packaging-print'],
    ]],
    ['name' => 'Writing & Content', 'slug' => 'writing-content', 'children' => [
        ['name' => 'Copywriting & sales pages', 'slug' => 'copywriting'],
        ['name' => 'Blog & editorial writing', 'slug' => 'blog-editorial'],
        ['name' => 'Technical writing', 'slug' => 'technical-writing'],
        ['name' => 'Translation & localization', 'slug' => 'translation'],
        ['name' => 'Scriptwriting & storytelling', 'slug' => 'scriptwriting'],
        ['name' => 'SEO content strategy', 'slug' => 'seo-content'],
    ]],
    ['name' => 'Marketing & Growth', 'slug' => 'marketing-growth', 'children' => [
        ['name' => 'Performance ads (Meta, Google)', 'slug' => 'performance-ads'],
        ['name' => 'Social media management', 'slug' => 'social-media'],
        ['name' => 'Email & lifecycle marketing', 'slug' => 'email-marketing'],
        ['name' => 'Influencer & partnerships', 'slug' => 'influencer-partnerships'],
        ['name' => 'Market research & insights', 'slug' => 'market-research'],
        ['name' => 'CRO & funnel optimization', 'slug' => 'cro-funnels'],
    ]],
    ['name' => 'Business & Operations', 'slug' => 'business-operations', 'children' => [
        ['name' => 'Virtual assistant & admin', 'slug' => 'virtual-assistant'],
        ['name' => 'Project & program management', 'slug' => 'project-management'],
        ['name' => 'Business analysis & strategy', 'slug' => 'business-strategy'],
        ['name' => 'Process & workflow design', 'slug' => 'process-design'],
        ['name' => 'HR & recruiting support', 'slug' => 'hr-recruiting'],
        ['name' => 'Customer support & success', 'slug' => 'customer-support'],
    ]],
    ['name' => 'Finance & Accounting', 'slug' => 'finance-accounting', 'children' => [
        ['name' => 'Bookkeeping', 'slug' => 'bookkeeping'],
        ['name' => 'Tax preparation & advisory', 'slug' => 'tax-advisory'],
        ['name' => 'Financial modelling', 'slug' => 'financial-modelling'],
        ['name' => 'Payroll processing', 'slug' => 'payroll'],
        ['name' => 'Audit support', 'slug' => 'audit-support'],
    ]],
    ['name' => 'Legal & Compliance', 'slug' => 'legal-compliance', 'children' => [
        ['name' => 'Contract drafting & review', 'slug' => 'contracts'],
        ['name' => 'Corporate & startup advisory', 'slug' => 'corporate-advisory'],
        ['name' => 'IP & trademarks', 'slug' => 'ip-trademarks'],
        ['name' => 'Regulatory compliance', 'slug' => 'regulatory-compliance'],
        ['name' => 'Dispute & mediation support', 'slug' => 'dispute-mediation'],
    ]],
    ['name' => 'Engineering & STEM', 'slug' => 'engineering-stem', 'children' => [
        ['name' => 'Civil & structural (design support)', 'slug' => 'civil-structural'],
        ['name' => 'Electrical & electronics', 'slug' => 'electrical-electronics'],
        ['name' => 'Mechanical CAD / drafting', 'slug' => 'mechanical-cad'],
        ['name' => 'Surveying & GIS', 'slug' => 'surveying-gis'],
        ['name' => 'Environmental assessments', 'slug' => 'environmental'],
    ]],
    ['name' => 'Trades & Field services', 'slug' => 'trades-field', 'children' => [
        ['name' => 'Electrical installations', 'slug' => 'electrical-install'],
        ['name' => 'Plumbing & HVAC', 'slug' => 'plumbing-hvac'],
        ['name' => 'Carpentry & interiors', 'slug' => 'carpentry-interiors'],
        ['name' => 'Generator & power systems', 'slug' => 'generator-power'],
        ['name' => 'Facility maintenance', 'slug' => 'facility-maintenance'],
    ]],
    ['name' => 'Education & Training', 'slug' => 'education-training', 'children' => [
        ['name' => 'Tutoring (STEM)', 'slug' => 'tutoring-stem'],
        ['name' => 'Tutoring (languages)', 'slug' => 'tutoring-languages'],
        ['name' => 'Corporate training & L&D', 'slug' => 'corporate-training'],
        ['name' => 'Curriculum & course design', 'slug' => 'curriculum-design'],
        ['name' => 'Exam prep & certifications', 'slug' => 'exam-prep'],
    ]],
    ['name' => 'Healthcare & Wellness', 'slug' => 'healthcare-wellness', 'children' => [
        ['name' => 'Telehealth coordination', 'slug' => 'telehealth-coord'],
        ['name' => 'Medical scribing & notes', 'slug' => 'medical-scribing'],
        ['name' => 'Nutrition & fitness coaching', 'slug' => 'nutrition-fitness'],
        ['name' => 'Mental health support (non-clinical)', 'slug' => 'mental-health-support'],
        ['name' => 'Health informatics & records', 'slug' => 'health-informatics'],
    ]],
    ['name' => 'Media & Events', 'slug' => 'media-events', 'children' => [
        ['name' => 'Photography', 'slug' => 'photography'],
        ['name' => 'Videography & livestream', 'slug' => 'videography-livestream'],
        ['name' => 'Podcast production', 'slug' => 'podcast-production'],
        ['name' => 'Event planning & production', 'slug' => 'event-planning'],
        ['name' => 'PR & communications', 'slug' => 'pr-comms'],
    ]],
    ['name' => 'Sales & Business development', 'slug' => 'sales-bd', 'children' => [
        ['name' => 'Lead generation & outbound', 'slug' => 'lead-generation'],
        ['name' => 'Inside sales & closing', 'slug' => 'inside-sales'],
        ['name' => 'Channel & partnerships', 'slug' => 'channel-partnerships'],
        ['name' => 'CRM setup & hygiene', 'slug' => 'crm-setup'],
    ]],
    ['name' => 'E-commerce & retail ops', 'slug' => 'ecommerce-retail', 'children' => [
        ['name' => 'Store setup (Shopify, Woo)', 'slug' => 'store-setup'],
        ['name' => 'Catalog & inventory ops', 'slug' => 'catalog-inventory'],
        ['name' => 'Fulfillment coordination', 'slug' => 'fulfillment'],
        ['name' => 'Marketplace management', 'slug' => 'marketplace-mgmt'],
    ]],
    ['name' => 'Agriculture & supply chain', 'slug' => 'agriculture-supply', 'children' => [
        ['name' => 'Farm advisory & agronomy', 'slug' => 'farm-advisory'],
        ['name' => 'Agri data & IoT', 'slug' => 'agri-data-iot'],
        ['name' => 'Logistics & cold chain', 'slug' => 'logistics-cold-chain'],
        ['name' => 'Import/export documentation', 'slug' => 'import-export-docs'],
    ]],
    ['name' => 'Real estate & property', 'slug' => 'real-estate', 'children' => [
        ['name' => 'Property research & listings', 'slug' => 'property-research'],
        ['name' => 'Facility & estate management', 'slug' => 'estate-management'],
        ['name' => 'Architecture visualization', 'slug' => 'architecture-viz'],
    ]],
    ['name' => 'Non-profit & community', 'slug' => 'nonprofit-community', 'children' => [
        ['name' => 'Grant writing', 'slug' => 'grant-writing'],
        ['name' => 'Community programs & outreach', 'slug' => 'community-outreach'],
        ['name' => 'Monitoring & evaluation', 'slug' => 'monitoring-evaluation'],
    ]],
    ['name' => 'Other / multi-disciplinary', 'slug' => 'other-multidisciplinary', 'children' => [
        ['name' => 'General consulting', 'slug' => 'general-consulting'],
        ['name' => 'Research & desk studies', 'slug' => 'research-desk'],
        ['name' => 'Miscellaneous gigs', 'slug' => 'misc-gigs'],
    ]],
];
