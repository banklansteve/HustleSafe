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
        'Microsoft Office',
        'Google Workspace',
        'Research',
        'Report writing',
    ],

    'by_parent' => [
        'technology-software' => [
            'HTML', 'CSS', 'JavaScript', 'TypeScript', 'Python', 'PHP', 'Java', 'C#', 'Go', 'Rust', 'C++', 'Ruby', 'Swift', 'Kotlin',
            'Web development', 'Software development', 'Coding', 'Programming', 'Frontend development', 'Backend development', 'Full-stack development',
            'React', 'Vue.js', 'Angular', 'Next.js', 'Node.js', 'Laravel', 'Django', 'Express', 'FastAPI', 'Spring Boot',
            'PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'REST API', 'GraphQL', 'API integration',
            'Docker', 'Kubernetes', 'AWS', 'Azure', 'GCP', 'CI/CD', 'Git', 'Linux',
            'Unit testing', 'Integration testing', 'TDD', 'Agile', 'Scrum',
            'UI integration', 'Responsive design', 'Web accessibility', 'Mobile development',
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
            'Digital marketing', 'Social media marketing', 'Social media management', 'Content marketing', 'Growth marketing',
            'Meta Ads', 'Google Ads', 'TikTok Ads', 'LinkedIn Ads', 'Performance marketing',
            'SEO', 'SEM', 'Google Analytics', 'GA4', 'Tag Manager',
            'Email marketing', 'Klaviyo', 'Mailchimp', 'HubSpot',
            'Social media strategy', 'Content calendar', 'Influencer marketing', 'Community management',
            'CRO', 'A/B testing', 'Funnel optimization', 'Market research', 'Brand marketing',
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
            'Music teaching', 'Piano', 'Guitar', 'Vocal coaching',
            'Dance instruction', 'Choreography', 'Sports coaching', 'Fitness instruction',
            'Driving instruction', 'Coding instruction', 'AI fundamentals', 'Vocational training',
        ],
        'healthcare-wellness' => [
            'Medical transcription', 'Patient scheduling', 'Health records',
            'Telehealth support', 'Nutrition coaching', 'Fitness coaching',
            'Mental health first aid', 'Care coordination', 'HIPAA awareness',
            'Personal training', 'Strength training', 'Yoga', 'Pilates', 'Meditation',
            'Herbal medicine', 'Traditional medicine', 'Counseling', 'Psychotherapy',
            'Wellness coaching', 'Stress management',
        ],
        'media-events' => [
            'Photography', 'Videography', 'Video editing', 'Live streaming',
            'Podcast editing', 'Sound mixing', 'Event planning', 'MC hosting',
            'DJ', 'Stage management', 'Lighting', 'Public relations',
            'Portrait photography', 'Product photography', 'Drone footage',
            'Event decoration', 'Backdrop design', 'Balloon decor', 'Floral arrangement',
            'Party rentals', 'Equipment rental', 'Venue sourcing', 'Vendor coordination',
            'Live band', 'Musician', 'Photo editing', 'Color grading',
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
        'home-office-cleaning' => [
            'Residential cleaning', 'Office cleaning', 'Deep cleaning', 'Move-out cleaning',
            'Post-construction cleaning', 'Carpet cleaning', 'Upholstery cleaning',
            'Window cleaning', 'Floor scrubbing & polishing', 'Sanitization', 'Disinfection',
            'Pest control', 'Fumigation', 'Drain cleaning', 'Waste disposal',
            'Eco-friendly products', 'Gardening', 'Lawn care', 'Reliability',
        ],
        'repairs-maintenance' => [
            'Appliance repair', 'Fault diagnosis', 'Electrical troubleshooting', 'Refrigeration',
            'Air conditioning repair', 'Soldering', 'Wiring', 'Component replacement',
            'Preventive maintenance', 'Generator servicing', 'Inverter repair', 'Solar systems',
            'Plumbing repair', 'Roofing repair', 'Aluminium fabrication', 'Spare parts sourcing',
            'Tool handling', 'Safety compliance',
        ],
        'automotive-services' => [
            'Auto mechanics', 'Engine diagnostics', 'Brake repair', 'Suspension repair',
            'Auto electrical', 'Car AC repair', 'Panel beating', 'Spray painting',
            'Dent removal', 'Wheel alignment', 'Tyre balancing', 'Vulcanizing',
            'Car detailing', 'Car wash', 'Window tinting', 'Auto upholstery',
            'OBD scanning', 'Vehicle servicing',
        ],
        'installation-assembly' => [
            'Solar installation', 'Inverter installation', 'AC installation', 'CCTV installation',
            'Alarm systems', 'Networking', 'WiFi setup', 'Fibre optics', 'Cable management',
            'Furniture assembly', 'Cabinet fitting', 'Mounting & rigging', 'Wall mounting',
            'Home theatre setup', 'Wiring', 'Blueprint reading', 'Tool handling', 'Safety compliance',
        ],
        'beauty-personal-care' => [
            'Barbing', 'Hairdressing', 'Hair braiding', 'Weave & extensions', 'Hair treatment',
            'Makeup artistry', 'Bridal makeup', 'Gele tying', 'Manicure', 'Pedicure', 'Nail art',
            'Eyelash extensions', 'Eyebrow shaping', 'Massage therapy', 'Facials', 'Skincare',
            'Hygiene & sanitation', 'Customer care',
        ],
        'laundry-textile-care' => [
            'Washing', 'Ironing', 'Pressing', 'Dry cleaning', 'Stain removal', 'Fabric care',
            'Garment steaming', 'Tailoring', 'Sewing', 'Alterations', 'Pattern cutting',
            'Bespoke tailoring', 'Shoe repair', 'Shoe shining', 'Leather care', 'Fabric dyeing',
            'Folding & packaging', 'Garment handling',
        ],
        'catering-food-services' => [
            'Catering', 'Cooking', 'Nigerian cuisine', 'Continental cuisine', 'Menu planning',
            'Food presentation', 'Meal prep', 'Baking', 'Pastry', 'Cake decorating', 'Confectionery',
            'Bartending', 'Cocktail mixing', 'Food safety', 'Hygiene (HACCP)', 'Event catering',
            'Portioning', 'Costing & budgeting',
        ],
        'transportation-logistics' => [
            'Driving', 'Defensive driving', 'Route planning', 'Logistics coordination', 'Haulage',
            'Loading & offloading', 'Moving & relocation', 'Packing', 'Furniture handling',
            'Dispatch', 'Last-mile delivery', 'Courier service', 'Fleet coordination',
            'Cargo handling', 'Inventory tracking', 'Navigation (GPS)', 'Customer service', 'Time management',
        ],
        'childcare-eldercare' => [
            'Childcare', 'Babysitting', 'Child safety', 'First aid', 'CPR', 'Meal preparation',
            'Nanny services', 'Early childhood care', 'Elderly care', 'Companion care', 'Patient care',
            'Medication reminders', 'Mobility assistance', 'Pet care', 'Dog walking', 'Pet grooming',
            'Pet training', 'Patience & empathy',
        ],
        'specialized-artisan' => [
            'Watch repair', 'Jewelry repair', 'Goldsmithing', 'Leatherwork', 'Bag repair',
            'Locksmithing', 'Key cutting', 'Printing', 'Photo framing', 'Lamination', 'Calligraphy',
            'Invitation design', 'Hand lettering', 'Upholstery', 'Furniture restoration',
            'Craftsmanship', 'Precision work', 'Tool handling',
        ],
        'nonprofit-community' => [
            'Grant writing', 'Proposal writing', 'Fundraising', 'Donor relations',
            'Community outreach', 'Program management', 'Monitoring & evaluation', 'Impact reporting',
            'Volunteer coordination', 'Stakeholder engagement', 'Needs assessment', 'Advocacy',
        ],
        'research-decision' => [
            'Research design', 'Survey design', 'Data collection', 'Data analysis',
            'Statistical analysis', 'Qualitative research', 'Stakeholder interviews', 'Policy analysis',
            'Report writing', 'Dashboards', 'Data visualization', 'Competitive analysis', 'Market intelligence',
        ],
        'other-multidisciplinary' => [
            'Consulting', 'Research', 'Analysis', 'Report writing', 'Problem solving',
            'Project coordination', 'Stakeholder management', 'Presentation',
        ],
    ],

    'by_leaf' => [
        'web-frontend' => ['React', 'Vue.js', 'Angular', 'Next.js', 'Tailwind CSS', 'HTML', 'CSS', 'SASS'],
        'web-backend' => ['Laravel', 'Node.js', 'Express', 'Django', 'FastAPI', 'Spring Boot', 'API design'],
        'fullstack-development' => ['React', 'Laravel', 'Node.js', 'PostgreSQL', 'Full-stack development', 'HTML', 'CSS', 'JavaScript', 'Web development', 'Software development'],
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

        // Home & Office Cleaning
        'residential-cleaning' => ['House cleaning', 'Room cleaning', 'Kitchen cleaning', 'Bathroom sanitizing', 'Dusting', 'Mopping', 'Vacuuming', 'Bed making'],
        'office-commercial-cleaning' => ['Office cleaning', 'Workstation cleaning', 'Restroom sanitation', 'Floor buffing', 'Glass partition cleaning', 'Common-area cleaning', 'Waste management'],
        'post-construction-cleaning' => ['Debris removal', 'Dust extraction', 'Paint & cement removal', 'Floor scrubbing', 'Window cleaning', 'Fine cleaning', 'Site clearance'],
        'move-in-out-cleaning' => ['Empty-home cleaning', 'Cabinet cleaning', 'Appliance cleaning', 'Wall wiping', 'Floor deep clean', 'Move-out checklist'],
        'carpet-upholstery-cleaning' => ['Carpet shampooing', 'Steam cleaning', 'Stain removal', 'Upholstery cleaning', 'Rug cleaning', 'Odour removal', 'Fabric protection'],
        'window-facade-cleaning' => ['Window washing', 'Glass cleaning', 'Facade cleaning', 'High-rise cleaning', 'Squeegee technique', 'Streak-free finish'],
        'deep-cleaning' => ['Deep cleaning', 'Descaling', 'Grout cleaning', 'Disinfection', 'Appliance deep clean', 'Detailed scrubbing'],
        'end-of-tenancy-cleaning' => ['End-of-tenancy cleaning', 'Inventory-standard cleaning', 'Oven cleaning', 'Limescale removal', 'Carpet cleaning', 'Wall cleaning'],
        'pest-control-fumigation' => ['Pest control', 'Fumigation', 'Termite treatment', 'Cockroach control', 'Bedbug treatment', 'Rodent control', 'Insecticide application', 'Safety handling'],
        'disinfection-sanitation' => ['Disinfection', 'Sanitization', 'Fogging', 'Surface sterilization', 'Hygiene compliance', 'Chemical handling'],
        'drain-cleaning' => ['Drain cleaning', 'Drain unclogging', 'Sewer maintenance', 'Pipe snaking', 'Hydro-jetting', 'Septic care'],
        'landscaping-gardening' => ['Landscaping', 'Gardening', 'Planting', 'Soil preparation', 'Hedge trimming', 'Lawn care', 'Garden maintenance'],
        'lawn-maintenance' => ['Lawn mowing', 'Grass cutting', 'Edging', 'Fertilizing', 'Weeding', 'Turf care'],
        'tree-trimming-removal' => ['Tree trimming', 'Pruning', 'Tree felling', 'Stump removal', 'Branch cutting', 'Chainsaw operation', 'Height safety'],
        'garden-design' => ['Garden design', 'Landscape design', 'Plant selection', 'Hardscaping', 'Irrigation planning', 'Layout planning'],

        // Repairs & Maintenance
        'ac-refrigeration-repair' => ['AC repair', 'AC servicing', 'Gas refill', 'Compressor repair', 'Refrigeration', 'Freezer repair', 'Cooling diagnostics'],
        'tv-entertainment-repair' => ['TV repair', 'LED/LCD repair', 'Screen replacement', 'Home theatre repair', 'Audio system repair', 'Board-level repair'],
        'washing-machine-repair' => ['Washing machine repair', 'Motor repair', 'Drum repair', 'Control board repair', 'Leak fixing', 'Appliance diagnostics'],
        'microwave-oven-repair' => ['Microwave repair', 'Oven repair', 'Heating element replacement', 'Magnetron repair', 'Thermostat repair'],
        'inverter-solar-repair' => ['Inverter repair', 'Solar panel repair', 'Battery diagnostics', 'Charge controller repair', 'Wiring fault repair'],
        'generator-repair-servicing' => ['Generator repair', 'Generator servicing', 'Engine maintenance', 'Carburetor cleaning', 'Oil change', 'AVR replacement'],
        'water-pump-repair' => ['Water pump repair', 'Pump installation', 'Pressure tank setup', 'Plumbing connection', 'Motor rewinding'],
        'phone-repair' => ['Phone repair', 'Screen replacement', 'Battery replacement', 'Charging port repair', 'Software flashing', 'Micro-soldering'],
        'computer-laptop-repair' => ['Laptop repair', 'Hardware troubleshooting', 'Screen replacement', 'OS reinstallation', 'Data recovery', 'Component upgrade'],
        'roofing-repair' => ['Roofing repair', 'Roof installation', 'Leak sealing', 'Sheet replacement', 'Gutter repair', 'Waterproofing'],
        'glass-aluminium-work' => ['Aluminium fabrication', 'Glass fitting', 'Window frames', 'Sliding doors', 'Curtain walling', 'Measurement & cutting'],
        'door-window-repair' => ['Door repair', 'Window repair', 'Hinge replacement', 'Lock fitting', 'Frame alignment', 'Seal replacement'],

        // Automotive Services
        'auto-mechanic' => ['Engine repair', 'Brake service', 'Suspension', 'Clutch repair', 'Diagnostics (OBD)', 'Oil change', 'Transmission repair'],
        'car-wash-detailing' => ['Car washing', 'Interior detailing', 'Exterior polishing', 'Waxing', 'Vacuuming', 'Engine bay cleaning'],
        'panel-beating-dent' => ['Panel beating', 'Dent removal', 'Bodywork', 'Spray painting', 'Rust treatment', 'Filler & sanding'],
        'tyre-services' => ['Tyre fitting', 'Vulcanizing', 'Wheel balancing', 'Wheel alignment', 'Puncture repair', 'Tyre rotation'],
        'car-ac-electrical' => ['Car AC repair', 'Auto electrical', 'Wiring', 'Battery service', 'Alternator repair', 'Sensor diagnostics'],
        'car-tinting' => ['Window tinting', 'Film application', 'Bubble-free finish', 'Tint cutting', 'UV film fitting'],
        'auto-upholstery' => ['Auto upholstery', 'Seat re-covering', 'Interior trim', 'Leather seats', 'Foam replacement', 'Stitching'],

        // Installation & Assembly
        'solar-inverter-installation' => ['Solar installation', 'Inverter installation', 'Battery wiring', 'Panel mounting', 'Load calculation', 'System commissioning'],
        'ac-installation' => ['AC installation', 'Split unit mounting', 'Copper piping', 'Vacuuming & gas charge', 'Bracket fitting'],
        'internet-wifi-setup' => ['WiFi setup', 'Router configuration', 'Network cabling', 'Access point setup', 'Signal optimization', 'Troubleshooting'],
        'cctv-security-installation' => ['CCTV installation', 'Camera mounting', 'DVR/NVR setup', 'Cabling', 'Remote viewing setup', 'Configuration'],
        'alarm-system-maintenance' => ['Alarm installation', 'Sensor setup', 'System testing', 'Maintenance', 'Fault diagnosis'],
        'fiber-optic-installation' => ['Fibre splicing', 'Cable laying', 'Termination', 'OTDR testing', 'Patch panel setup'],
        'furniture-assembly' => ['Furniture assembly', 'Flat-pack assembly', 'Wardrobe assembly', 'Bed assembly', 'Fittings & fixings', 'Tool handling'],
        'kitchen-cabinet-installation' => ['Cabinet installation', 'Worktop fitting', 'Carpentry', 'Levelling', 'Hinge & handle fitting'],
        'shelving-storage-setup' => ['Shelving installation', 'Wall anchoring', 'Storage setup', 'Bracket mounting', 'Levelling'],
        'home-theatre-setup' => ['Home theatre setup', 'Speaker mounting', 'AV wiring', 'Surround sound calibration', 'TV wall mounting'],

        // Beauty & Personal Care
        'barbing-haircut' => ['Haircut', 'Fades', 'Beard grooming', 'Clipper work', 'Razor lining', 'Hair styling'],
        'hair-braiding-extensions' => ['Hair braiding', 'Cornrows', 'Box braids', 'Weave installation', 'Crochet braids', 'Wig fitting'],
        'hair-treatment-therapy' => ['Hair treatment', 'Scalp therapy', 'Deep conditioning', 'Relaxer application', 'Hair coloring', 'Steaming'],
        'makeup-services' => ['Makeup artistry', 'Bridal makeup', 'Editorial makeup', 'Gele tying', 'Airbrush makeup', 'Lash application'],
        'nail-care' => ['Manicure', 'Pedicure', 'Gel polish', 'Acrylic nails', 'Nail art', 'Cuticle care'],
        'eyelash-brow-services' => ['Eyelash extensions', 'Lash lifting', 'Brow shaping', 'Microblading', 'Brow tinting', 'Threading'],
        'massage-therapy' => ['Massage therapy', 'Swedish massage', 'Deep tissue massage', 'Relaxation massage', 'Aromatherapy', 'Reflexology'],
        'spa-facial-treatments' => ['Facials', 'Body treatments', 'Exfoliation', 'Steam therapy', 'Skin analysis', 'Spa hygiene'],
        'skincare-consultation' => ['Skincare consultation', 'Skin analysis', 'Product recommendation', 'Acne care', 'Routine planning'],

        // Laundry & Textile Care
        'wash-and-iron' => ['Washing', 'Ironing', 'Pressing', 'Folding', 'Stain treatment', 'Fabric sorting'],
        'dry-cleaning' => ['Dry cleaning', 'Delicate fabrics', 'Suit cleaning', 'Stain removal', 'Garment pressing'],
        'express-laundry' => ['Express laundry', 'Same-day turnaround', 'Bulk washing', 'Quick pressing', 'Order tracking'],
        'tailoring-alterations' => ['Tailoring', 'Alterations', 'Hemming', 'Resizing', 'Zip replacement', 'Sewing'],
        'custom-tailoring' => ['Bespoke tailoring', 'Pattern making', 'Measurement taking', 'Suit making', 'Native wear', 'Wedding wear'],
        'shoe-repair-shining' => ['Shoe repair', 'Sole replacement', 'Heel repair', 'Shoe shining', 'Stitching', 'Polishing'],
        'leather-care' => ['Leather care', 'Leather conditioning', 'Leather repair', 'Cleaning', 'Color restoration'],
        'fabric-dyeing-restoration' => ['Fabric dyeing', 'Tie & dye (adire)', 'Colour restoration', 'Fabric treatment', 'Garment restoration'],

        // Catering & Food Services
        'event-catering' => ['Event catering', 'Buffet setup', 'Menu planning', 'Bulk cooking', 'Food presentation', 'Serving coordination'],
        'meal-prep-delivery' => ['Meal prep', 'Portion control', 'Diet meals', 'Food packaging', 'Delivery coordination', 'Menu rotation'],
        'local-dishes-soups' => ['Jollof rice', 'Nigerian soups', 'Egusi', 'Pounded yam', 'Local cuisine', 'Stew preparation'],
        'private-chef' => ['Private chef', 'Menu design', 'Fine dining', 'Continental dishes', 'Plating', 'Kitchen management'],
        'cake-pastry-baking' => ['Cake baking', 'Cake decorating', 'Fondant work', 'Cupcakes', 'Pastries', 'Icing & frosting'],
        'bread-confectionery' => ['Bread baking', 'Confectionery', 'Dough making', 'Pastry production', 'Small chops'],
        'beverage-cocktail-service' => ['Bartending', 'Cocktail mixing', 'Mocktails', 'Drink service', 'Beverage setup', 'Garnishing'],
        'food-photography' => ['Food photography', 'Food styling', 'Lighting', 'Photo editing', 'Menu photography'],

        // Transportation & Logistics
        'van-driver-hire' => ['Driving', 'Goods transport', 'Route planning', 'Loading', 'Vehicle handling', 'Safe delivery'],
        'truck-haulage' => ['Truck driving', 'Haulage', 'Heavy goods transport', 'Cargo securing', 'Load planning'],
        'house-moving-relocation' => ['House moving', 'Packing', 'Furniture handling', 'Loading & offloading', 'Disassembly & reassembly'],
        'office-relocation' => ['Office relocation', 'Equipment handling', 'Packing', 'IT relocation', 'Inventory tracking'],
        'warehouse-storage-labor' => ['Warehouse handling', 'Storage management', 'Loading', 'Inventory', 'Stacking & racking'],
        'same-day-delivery' => ['Same-day delivery', 'Dispatch riding', 'Route optimization', 'Parcel handling', 'Proof of delivery'],
        'interstate-courier' => ['Interstate courier', 'Long-haul delivery', 'Parcel tracking', 'Cargo handling', 'Logistics coordination'],
        'document-delivery' => ['Document delivery', 'Confidential handling', 'Dispatch', 'Proof of delivery', 'Route planning'],
        'parcel-delivery' => ['Parcel delivery', 'Last-mile delivery', 'Package handling', 'Dispatch', 'Customer service'],
        'fragile-items-handling' => ['Fragile handling', 'Careful packing', 'Cushioning', 'Secure transport', 'Damage prevention'],

        // Childcare & Eldercare
        'babysitting' => ['Babysitting', 'Child supervision', 'Play activities', 'Feeding', 'First aid', 'Diaper changing'],
        'nanny-services' => ['Nanny services', 'Child development', 'Meal prep', 'Homework help', 'Routine management'],
        'daycare-coordination' => ['Daycare coordination', 'Group supervision', 'Activity planning', 'Child safety', 'Record keeping'],
        'school-pickup-dropoff' => ['School run', 'Safe driving', 'Child supervision', 'Punctuality', 'Route planning'],
        'elderly-companion-care' => ['Elderly companionship', 'Mobility assistance', 'Meal preparation', 'Medication reminders', 'Emotional support'],
        'home-nursing-assistance' => ['Home nursing', 'Patient care', 'Vital signs monitoring', 'Wound care', 'Medication administration', 'First aid'],
        'pet-sitting-walking' => ['Pet sitting', 'Dog walking', 'Feeding', 'Pet supervision', 'Animal care'],
        'pet-grooming' => ['Pet grooming', 'Bathing', 'Nail trimming', 'Coat clipping', 'De-shedding'],
        'pet-training' => ['Pet training', 'Obedience training', 'Behaviour correction', 'Leash training', 'Positive reinforcement'],

        // Specialized & Artisan Services
        'watch-jewelry-repair' => ['Watch repair', 'Battery replacement', 'Jewelry repair', 'Goldsmithing', 'Stone setting', 'Polishing'],
        'bag-leather-repair' => ['Bag repair', 'Leather repair', 'Zip replacement', 'Stitching', 'Restoration'],
        'locksmith-key' => ['Locksmithing', 'Key cutting', 'Lock installation', 'Lock repair', 'Rekeying'],
        'printing-framing' => ['Printing', 'Photo framing', 'Canvas printing', 'Lamination', 'Mounting', 'Large-format printing'],
        'calligraphy-invitations' => ['Calligraphy', 'Hand lettering', 'Invitation design', 'Envelope addressing', 'Custom stationery'],
        'furniture-upholstery' => ['Upholstery', 'Furniture restoration', 'Re-covering', 'Foam padding', 'Fabric selection', 'Frame repair'],

        // Media & Events (new subcategories)
        'photo-video-editing' => ['Photo editing', 'Video editing', 'Lightroom', 'Premiere Pro', 'Color grading', 'Retouching'],
        'event-decoration-setup' => ['Event decoration', 'Backdrop design', 'Balloon decor', 'Floral arrangement', 'Draping', 'Stage setup'],
        'party-equipment-rentals' => ['Equipment rental', 'Canopy & chairs', 'PA systems', 'Lighting rental', 'Logistics', 'Setup & teardown'],
        'venue-sourcing-management' => ['Venue sourcing', 'Vendor coordination', 'Booking management', 'Site inspection', 'Budget management'],
        'live-band-musicians' => ['Live performance', 'Instrument playing', 'Vocals', 'Band coordination', 'Sound check'],

        // Education & Training (new subcategories)
        'music-lessons' => ['Music teaching', 'Piano', 'Guitar', 'Drums', 'Music theory', 'Vocal coaching'],
        'dance-classes' => ['Dance instruction', 'Choreography', 'Afro dance', 'Contemporary dance', 'Traditional dance'],
        'sports-coaching' => ['Sports coaching', 'Football coaching', 'Fitness drills', 'Team training', 'Athletic conditioning'],
        'driving-lessons' => ['Driving instruction', 'Defensive driving', 'Road safety', 'Highway code', 'Manual & automatic'],
        'computer-it-training' => ['Computer training', 'Coding instruction', 'Python', 'Web development', 'AI fundamentals', 'Microsoft Office'],
        'trade-skills-workshops' => ['Vocational training', 'Welding training', 'Electrical training', 'Plumbing workshops', 'Hands-on instruction'],

        // Healthcare & Wellness (new subcategories)
        'fitness-coaching' => ['Personal training', 'Strength training', 'HIIT', 'Home workouts', 'Fitness programming'],
        'yoga-pilates' => ['Yoga instruction', 'Pilates', 'Meditation', 'Breathwork', 'Flexibility training'],
        'traditional-herbal-medicine' => ['Herbal medicine', 'Traditional medicine', 'Herbal remedies', 'Wellness consultation'],
        'therapy-counseling' => ['Counseling', 'Psychotherapy', 'Mental health support', 'Active listening', 'CBT basics'],

        // Technology & Software (additional leaves)
        'api-integration' => ['API integration', 'REST API', 'GraphQL', 'Webhooks', 'OAuth', 'Third-party integrations', 'ERP integration', 'Payment gateway integration'],

        // Design & Creative
        'brand-identity' => ['Brand identity', 'Logo design', 'Brand guidelines', 'Visual identity', 'Typography', 'Color palette'],
        'graphic-illustration' => ['Graphic design', 'Illustration', 'Adobe Illustrator', 'Adobe Photoshop', 'Vector art', 'Infographics'],
        'motion-video' => ['Motion design', 'After Effects', 'Premiere Pro', 'Video editing', 'Animation', 'Motion graphics'],
        '3d-visualization' => ['3D modeling', 'Blender', 'Cinema 4D', 'Product visualization', 'Rendering', '3D animation'],
        'packaging-print' => ['Packaging design', 'Print design', 'Adobe InDesign', 'Die-cut design', 'Label design', 'Prepress'],

        // Writing & Content
        'copywriting' => ['Copywriting', 'Sales copy', 'Landing page copy', 'Ad copy', 'Email copy', 'Conversion copy'],
        'blog-editorial' => ['Blog writing', 'Editorial writing', 'Content writing', 'Article writing', 'Long-form content'],
        'technical-writing' => ['Technical writing', 'Documentation', 'API documentation', 'User manuals', 'SOP writing'],
        'translation' => ['Translation', 'Localization', 'Proofreading', 'Bilingual editing', 'Transcreation'],
        'scriptwriting' => ['Scriptwriting', 'Storytelling', 'Narrative design', 'Video scripts', 'Podcast scripts'],
        'seo-content' => ['SEO', 'SEO writing', 'Content strategy', 'Keyword research', 'On-page SEO', 'Content optimization'],

        // Marketing & Growth
        'performance-ads' => ['Meta Ads', 'Google Ads', 'TikTok Ads', 'LinkedIn Ads', 'Performance marketing', 'PPC', 'Ad optimization'],
        'social-media' => ['Social media marketing', 'Social media management', 'Content calendar', 'Community management', 'Instagram', 'Facebook', 'LinkedIn', 'TikTok'],
        'email-marketing' => ['Email marketing', 'Klaviyo', 'Mailchimp', 'Lifecycle marketing', 'Newsletter', 'Drip campaigns'],
        'influencer-partnerships' => ['Influencer marketing', 'Partnership outreach', 'Brand collaborations', 'Affiliate marketing'],
        'market-research' => ['Market research', 'Competitive analysis', 'Survey design', 'Consumer insights', 'Focus groups'],
        'cro-funnels' => ['CRO', 'Funnel optimization', 'A/B testing', 'Landing page optimization', 'Conversion rate optimization'],

        // Business & Operations
        'virtual-assistant' => ['Virtual assistance', 'Calendar management', 'Data entry', 'Email management', 'Travel booking', 'Admin support'],
        'project-management' => ['Project management', 'Agile', 'Scrum', 'Asana', 'Jira', 'Monday.com', 'Stakeholder management'],
        'business-strategy' => ['Business analysis', 'Strategy consulting', 'Market analysis', 'Business planning', 'SWOT analysis'],
        'process-design' => ['Process design', 'Workflow optimization', 'SOP writing', 'Process documentation', 'Automation'],
        'hr-recruiting' => ['Recruiting', 'HR support', 'Candidate sourcing', 'Interview coordination', 'Onboarding'],
        'customer-support' => ['Customer support', 'Customer success', 'Zendesk', 'Intercom', 'Ticket management', 'Live chat'],

        // Finance & Accounting
        'bookkeeping' => ['Bookkeeping', 'QuickBooks', 'Xero', 'Reconciliation', 'Accounts payable', 'Accounts receivable'],
        'tax-advisory' => ['Tax preparation', 'Tax advisory', 'VAT compliance', 'Tax filing', 'Financial compliance'],
        'financial-modelling' => ['Financial modelling', 'Excel modeling', 'Forecasting', 'Budgeting', 'Valuation'],
        'payroll' => ['Payroll processing', 'PAYE', 'Pension administration', 'Salary computation'],
        'audit-support' => ['Audit support', 'Financial reporting', 'Internal controls', 'Compliance documentation'],

        // Legal & Compliance
        'contracts' => ['Contract drafting', 'Contract review', 'NDA', 'Legal research', 'Agreement negotiation'],
        'corporate-advisory' => ['Corporate law', 'Startup advisory', 'Company formation', 'Governance', 'Board support'],
        'ip-trademarks' => ['IP law', 'Trademark filing', 'Copyright', 'Patent support', 'Brand protection'],
        'regulatory-compliance' => ['Regulatory compliance', 'Policy drafting', 'Risk assessment', 'Compliance audits'],
        'dispute-mediation' => ['Dispute resolution', 'Mediation', 'Conflict resolution', 'Negotiation'],

        // Engineering & STEM
        'civil-structural' => ['Structural analysis', 'Civil engineering', 'AutoCAD', 'Revit', 'Technical drawings', 'Quantity surveying'],
        'electrical-electronics' => ['Electrical design', 'Circuit design', 'Electronics', 'PCB design', 'Power systems'],
        'mechanical-cad' => ['Mechanical drafting', 'SolidWorks', 'CAD design', '3D modeling', 'Technical drawings'],
        'surveying-gis' => ['Surveying', 'GIS', 'Mapping', 'Land surveying', 'Topographic surveys'],
        'environmental' => ['Environmental assessment', 'EIA', 'Sustainability reporting', 'Environmental compliance'],

        // Sales & BD
        'lead-generation' => ['Lead generation', 'Cold email', 'Cold calling', 'LinkedIn outreach', 'Prospecting'],
        'inside-sales' => ['Inside sales', 'Closing', 'Negotiation', 'Pipeline management', 'Demo calls'],
        'channel-partnerships' => ['Channel partnerships', 'Partner management', 'BD outreach', 'Alliance building'],
        'crm-setup' => ['CRM setup', 'Salesforce', 'HubSpot CRM', 'Pipeline hygiene', 'Sales automation'],

        // E-commerce & Retail
        'store-setup' => ['Shopify', 'WooCommerce', 'Store setup', 'Theme customization', 'Payment setup'],
        'catalog-inventory' => ['Product listing', 'Catalog management', 'Inventory management', 'SKU management'],
        'fulfillment' => ['Order fulfillment', 'Logistics coordination', 'Shipping', 'Returns processing'],
        'marketplace-mgmt' => ['Marketplace management', 'Amazon seller', 'Jumia seller', 'Listing optimization'],

        // Agriculture & Supply chain
        'agri-data-iot' => ['Agri data', 'IoT sensors', 'Farm monitoring', 'Precision agriculture', 'Data analysis'],
        'logistics-cold-chain' => ['Cold chain logistics', 'Supply chain', 'Temperature monitoring', 'Distribution'],
        'import-export-docs' => ['Import/export documentation', 'Customs clearance', 'Trade compliance', 'Shipping docs'],

        // Real estate
        'property-research' => ['Property research', 'Market analysis', 'Listing preparation', 'Comparative market analysis'],
        'estate-management' => ['Property management', 'Tenant relations', 'Facility management', 'Lease administration'],
        'architecture-viz' => ['Architecture visualization', '3D rendering', 'SketchUp', 'Revit', 'Interior visualization'],

        // Non-profit & Community
        'grant-writing' => ['Grant writing', 'Proposal writing', 'Fundraising', 'Donor relations'],
        'community-outreach' => ['Community outreach', 'Program coordination', 'Stakeholder engagement', 'Advocacy'],
        'monitoring-evaluation' => ['Monitoring & evaluation', 'Impact reporting', 'M&E frameworks', 'Data collection'],

        // Gaming & Interactive
        'game-design-economy' => ['Game design', 'Economy design', 'Game balancing', 'GDD writing', 'Level design'],
        'unity-unreal-support' => ['Unity', 'Unreal Engine', 'C#', 'Blueprints', 'Game development'],
        'liveops-community' => ['Live ops', 'Community moderation', 'Player support', 'Event management', 'Discord moderation'],
        'game-narrative' => ['Narrative design', 'Quest writing', 'Dialogue writing', 'World building', 'Storytelling'],

        // Research & Decision support
        'survey-enumerator' => ['Survey design', 'Data collection', 'Enumerator training', 'Field research'],
        'policy-memos' => ['Policy analysis', 'Policy memos', 'Stakeholder packs', 'Briefing notes'],
        'insight-decks' => ['Data visualization', 'Dashboards', 'Insight decks', 'PowerPoint', 'Presentation design'],
        'competitive-intel' => ['Competitive intelligence', 'Market scanning', 'Benchmarking', 'Industry research'],

        // Other / multi-disciplinary
        'general-consulting' => ['Consulting', 'Problem solving', 'Strategy', 'Analysis', 'Recommendations'],
        'research-desk' => ['Desk research', 'Literature review', 'Data synthesis', 'Report writing'],
        'misc-gigs' => ['General tasks', 'Flexible support', 'Ad hoc work', 'Multi-skilled assistance'],

        // Education & Training (additional)
        'tutoring-stem' => ['Mathematics tutoring', 'Physics tutoring', 'Chemistry tutoring', 'Biology tutoring', 'STEM tutoring'],
        'tutoring-languages' => ['English tutoring', 'French tutoring', 'IELTS prep', 'Language instruction', 'Grammar coaching'],
        'corporate-training' => ['Corporate training', 'L&D', 'Workshop facilitation', 'Presentation skills', 'Team training'],
        'curriculum-design' => ['Curriculum design', 'Course design', 'Lesson planning', 'Learning objectives', 'Assessment design'],
        'exam-prep' => ['WAEC prep', 'JAMB prep', 'IELTS prep', 'Exam coaching', 'Test preparation'],

        // Healthcare & Wellness (additional)
        'nutrition-fitness' => ['Nutrition coaching', 'Diet planning', 'Meal planning', 'Fitness nutrition', 'Weight management'],
        'mental-health-support' => ['Mental health support', 'Active listening', 'Peer support', 'Wellness coaching'],
        'telehealth-coord' => ['Telehealth coordination', 'Patient scheduling', 'Appointment booking', 'Health records'],
        'medical-scribing' => ['Medical scribing', 'Clinical notes', 'EMR documentation', 'Medical transcription'],
        'health-informatics' => ['Health informatics', 'Health records', 'HIPAA awareness', 'Data management'],

        // Media & Events (additional)
        'videography-livestream' => ['Videography', 'Live streaming', 'Camera operation', 'Video production', 'OBS Studio'],
        'podcast-production' => ['Podcast production', 'Audio editing', 'Podcast editing', 'Sound mixing', 'Audacity'],
        'event-planning' => ['Event planning', 'Event coordination', 'Vendor management', 'Budget planning', 'Run of show'],
        'events-mc' => ['MC hosting', 'Event hosting', 'Public speaking', 'Stage presence', 'Audience engagement'],
        'events-dj' => ['DJ', 'Music mixing', 'Sound engineering', 'Event entertainment', 'Serato'],
        'pr-comms' => ['Public relations', 'Press releases', 'Media relations', 'Communications', 'Crisis comms'],

        // Trades & Field (additional)
        'carpentry-interiors' => ['Carpentry', 'Furniture making', 'Woodwork', 'Cabinet making', 'Interior fittings'],
        'bricklaying-masonry' => ['Bricklaying', 'Masonry', 'Block laying', 'Plastering', 'Foundation work'],
        'tiling-flooring' => ['Tiling', 'Flooring', 'Ceramic tiles', 'Marble flooring', 'Grouting'],
        'painting-decorating' => ['Painting', 'Decorating', 'Wall finishing', 'Spray painting', 'Colour matching'],
        'welding-fabrication' => ['Welding', 'Metal fabrication', 'Steel work', 'Arc welding', 'MIG welding'],
        'generator-power' => ['Generator installation', 'Power systems', 'Inverter systems', 'Electrical wiring', 'Load calculation'],
        'facility-maintenance' => ['Facility maintenance', 'Preventive maintenance', 'Building maintenance', 'HVAC maintenance'],
    ],

];
