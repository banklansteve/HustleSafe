<?php

use App\Enums\CredentialType;
use App\Enums\PortfolioStatus;
use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestStartTiming;
use App\Enums\QuestStatus;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\ActivityLog;
use App\Models\ContentReport;
use App\Models\FreelancerCredential;
use App\Models\LocalGovernment;
use App\Models\LoginEvent;
use App\Models\NewsletterSubscriber;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\Quest;
use App\Models\QuestBookmark;
use App\Models\QuestCategory;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Models\QuestFile;
use App\Models\QuestFreelancerInvite;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\ReviewAttachment;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserVerification;

$text = fn (int $max = 255) => ['type' => 'text', 'rules' => "nullable|string|max:{$max}"];
$reqText = fn (int $max = 255) => ['type' => 'text', 'rules' => "required|string|max:{$max}"];
$textarea = fn (int $max = 5000) => ['type' => 'textarea', 'rules' => "nullable|string|max:{$max}"];
$int = ['type' => 'integer', 'rules' => 'nullable|integer|min:0'];
$bool = ['type' => 'boolean', 'rules' => 'nullable|boolean'];
$email = ['type' => 'email', 'rules' => 'required|email|max:255'];
$date = ['type' => 'date', 'rules' => 'nullable|date'];
$json = ['type' => 'json', 'rules' => 'nullable|json'];
$money = ['type' => 'money_minor', 'rules' => 'nullable|numeric|min:0'];
$relation = fn (string $resource, string $label = 'name') => [
    'type' => 'relation',
    'relation_resource' => $resource,
    'option_label' => $label,
    'rules' => 'nullable|integer',
];
$select = fn (array $options) => [
    'type' => 'select',
    'options' => collect($options)->map(fn ($label, $value) => [
        'value' => is_int($value) ? $label : $value,
        'label' => is_int($value) ? str($label)->headline()->toString() : $label,
    ])->values()->all(),
    'rules' => 'nullable|string|max:255',
];
$enum = fn (string $enumClass) => $select(collect($enumClass::cases())
    ->mapWithKeys(fn ($case) => [$case->value => str($case->value)->replace('_', ' ')->headline()->toString()])
    ->all());

$userEditFields = [
    'username', 'first_name', 'last_name', 'name', 'email', 'phone', 'nin', 'bvn', 'gender', 'date_of_birth',
    'company_name', 'address_line', 'city', 'state_id', 'local_government_id', 'account_type', 'role_id',
    'profession', 'bio', 'headline', 'hourly_rate_min', 'hourly_rate_max', 'years_experience', 'availability',
    'verification_tier', 'onboarding_step', 'job_title', 'company_size', 'timezone', 'locale', 'avatar_url',
    'hide_online_presence', 'under_review_at', 'banned_at', 'ban_reason',
];

$questEditFields = [
    'title', 'description', 'status', 'escrow_status', 'client_id', 'freelancer_id', 'quest_category_id',
    'state_id', 'local_government_id', 'city', 'visibility', 'budget_amount_minor', 'max_offers',
    'scheduled_start_date', 'estimated_completion_days', 'estimated_delivery_date', 'due_at',
    'project_type', 'team_size', 'start_timing', 'availability_need',
    'freelancer_location_pref', 'dispute_opened', 'escrow_funded_at', 'completed_at',
];

$proposalEditFields = [
    'quest_id', 'freelancer_id', 'status', 'pitch', 'scope_detail', 'warranty_terms',
    'proposed_completion_date', 'planned_start_date', 'planned_finish_date', 'estimated_duration_days',
    'corrections_included', 'corrections_rounds', 'progress_report_frequency', 'materials',
    'pricing_snapshot', 'quoted_amount_minor',
];

return [
    'resources' => [
        'users' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 1,
            'label' => 'Accounts',
            'description' => 'Member accounts — edit profile, suspend, or remove. Super admins are protected.',
            'model' => User::class,
            'creatable' => true,
            'deletable' => true,
            'editable' => true,
            'list_columns' => ['id', 'name', 'email', 'account_type', 'is_suspended', 'created_at'],
            'search_columns' => ['name', 'email', 'username'],
            'with' => ['role:id,slug,name'],
            'fields' => [
                'username' => $reqText(80),
                'first_name' => [...$reqText(80), 'label' => 'First name'],
                'last_name' => [...$reqText(80), 'label' => 'Last name'],
                'name' => $reqText(160),
                'email' => $email,
                'phone' => $text(32),
                'nin' => $text(32),
                'bvn' => $text(32),
                'gender' => $text(32),
                'date_of_birth' => $date,
                'company_name' => $text(160),
                'address_line' => $text(255),
                'city' => $text(120),
                'state_id' => $relation('states'),
                'local_government_id' => $relation('local_governments'),
                'account_type' => $select(['client' => 'Client', 'freelancer' => 'Freelancer', 'admin' => 'Admin']),
                'role_id' => $relation('roles'),
                'profession' => $text(120),
                'bio' => $textarea(5000),
                'headline' => $text(200),
                'hourly_rate_min' => $money,
                'hourly_rate_max' => $money,
                'years_experience' => $int,
                'availability' => $text(64),
                'verification_tier' => $text(32),
                'onboarding_step' => $text(64),
                'job_title' => $text(120),
                'company_size' => $text(64),
                'timezone' => $text(64),
                'locale' => $text(16),
                'avatar_url' => $text(500),
                'hide_online_presence' => $bool,
                'under_review_at' => $date,
                'banned_at' => $date,
                'ban_reason' => $textarea(1000),
            ],
            'create_fields' => ['username', 'first_name', 'last_name', 'email', 'phone', 'account_type', 'role_id'],
            'edit_fields' => $userEditFields,
            'actions' => ['suspend', 'activity_log'],
        ],

        'roles' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 7,
            'sidebar_indent' => true,
            'label' => 'Roles',
            'description' => 'Account role records used for access control.',
            'model' => Role::class,
            'creatable' => true,
            'list_columns' => ['id', 'name', 'slug', 'created_at'],
            'search_columns' => ['name', 'slug'],
            'fields' => [
                'name' => $reqText(120),
                'slug' => $reqText(120),
            ],
            'create_fields' => ['name', 'slug'],
            'edit_fields' => ['name', 'slug'],
        ],

        'user_verifications' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 2,
            'sidebar_indent' => true,
            'label' => 'Verifications & docs',
            'description' => 'ID, insurance, and credential verification queue.',
            'model' => UserVerification::class,
            'creatable' => true,
            'list_columns' => ['id', 'user', 'category', 'status', 'submitted_at'],
            'search_columns' => ['category', 'status', 'verification_type'],
            'search_user_columns' => ['name', 'email', 'first_name', 'last_name'],
            'with' => ['user:id,name,email'],
            'fields' => [
                'user_id' => [...$relation('users'), 'rules' => 'required|integer|exists:users,id'],
                'category' => $enum(UserVerificationCategory::class),
                'status' => $enum(UserVerificationStatus::class),
                'rejection_reason' => $textarea(500),
                'document_paths' => [...$json, 'type' => 'key_value'],
                'metadata' => [...$json, 'type' => 'key_value'],
                'reviewed_by' => $relation('users'),
                'expires_at' => $date,
            ],
            'create_fields' => ['user_id', 'category', 'status'],
            'edit_fields' => ['status', 'rejection_reason', 'document_paths', 'metadata', 'reviewed_by', 'expires_at'],
        ],

        'freelancer_credentials' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 3,
            'sidebar_indent' => true,
            'label' => 'Credentials & insurance',
            'description' => 'Licences, certifications, and insurance documents.',
            'model' => FreelancerCredential::class,
            'creatable' => true,
            'list_columns' => ['id', 'user_id', 'credential_type', 'title', 'is_verified', 'expires_on'],
            'with' => ['user:id,name,email'],
            'fields' => [
                'user_id' => [...$relation('users'), 'rules' => 'required|integer|exists:users,id'],
                'credential_type' => $enum(CredentialType::class),
                'title' => $reqText(160),
                'issuing_authority' => $text(160),
                'reference_number' => $text(80),
                'is_verified' => $bool,
                'is_public' => $bool,
            ],
            'create_fields' => ['user_id', 'credential_type', 'title', 'issuing_authority', 'reference_number'],
            'edit_fields' => ['credential_type', 'title', 'issuing_authority', 'reference_number', 'is_verified', 'is_public'],
        ],

        'user_follows' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 4,
            'sidebar_indent' => true,
            'label' => 'Follows',
            'description' => 'Follow relationships between members.',
            'model' => UserFollow::class,
            'creatable' => true,
            'list_columns' => ['id', 'follower_id', 'following_id', 'created_at'],
            'with' => ['follower:id,name,email', 'following:id,name,email'],
            'fields' => [
                'follower_id' => [...$relation('users'), 'relation_name' => 'follower', 'rules' => 'required|integer|exists:users,id'],
                'following_id' => [...$relation('users'), 'relation_name' => 'following', 'rules' => 'required|integer|exists:users,id'],
            ],
            'create_fields' => ['follower_id', 'following_id'],
            'edit_fields' => ['follower_id', 'following_id'],
        ],

        'login_events' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 5,
            'sidebar_indent' => true,
            'label' => 'Login events',
            'description' => 'Authentication history (read & purge).',
            'model' => LoginEvent::class,
            'creatable' => false,
            'editable' => false,
            'list_columns' => ['id', 'user_id', 'ip_address', 'logged_in_at'],
            'with' => ['user:id,name,email'],
            'fields' => [
                'user_id' => [...$relation('users'), 'relation_name' => 'user'],
            ],
            'create_fields' => [],
            'edit_fields' => [],
        ],

        'activity_logs' => [
            'group' => 'People & access',
            'sidebar_section' => 'Users',
            'sidebar_order' => 10,
            'sidebar_sort' => 6,
            'sidebar_indent' => true,
            'label' => 'Activity logs',
            'description' => 'Platform activity entries tied to members.',
            'model' => ActivityLog::class,
            'creatable' => false,
            'editable' => false,
            'list_columns' => ['id', 'subject_user_id', 'type', 'title', 'created_at'],
            'search_columns' => ['type', 'title'],
            'with' => ['subject:id,name,email', 'actor:id,name,email'],
            'fields' => [
                'subject_user_id' => [...$relation('users'), 'relation_name' => 'subject'],
                'actor_id' => [...$relation('users'), 'relation_name' => 'actor'],
            ],
            'create_fields' => [],
            'edit_fields' => [],
        ],

        'states' => [
            'group' => 'Locations',
            'sidebar_section' => 'Locations',
            'sidebar_order' => 50,
            'sidebar_sort' => 1,
            'label' => 'States',
            'description' => 'Nigerian states reference data.',
            'model' => State::class,
            'creatable' => true,
            'list_columns' => ['id', 'code', 'name'],
            'search_columns' => ['code', 'name'],
            'fields' => [
                'code' => $reqText(8),
                'name' => $reqText(120),
            ],
            'create_fields' => ['code', 'name'],
            'edit_fields' => ['code', 'name'],
        ],

        'local_governments' => [
            'group' => 'Locations',
            'sidebar_section' => 'Locations',
            'sidebar_order' => 50,
            'sidebar_sort' => 2,
            'sidebar_indent' => true,
            'label' => 'Local governments',
            'description' => 'LGA records linked to states.',
            'model' => LocalGovernment::class,
            'creatable' => true,
            'list_columns' => ['id', 'state_id', 'name'],
            'search_columns' => ['name'],
            'with' => ['state:id,name,code'],
            'fields' => [
                'state_id' => [...$relation('states'), 'rules' => 'required|integer|exists:states,id'],
                'name' => $reqText(160),
            ],
            'create_fields' => ['state_id', 'name'],
            'edit_fields' => ['state_id', 'name'],
        ],

        'quest_categories' => [
            'group' => 'Quests & commerce',
            'sidebar_section' => 'Quests',
            'sidebar_order' => 20,
            'sidebar_sort' => 3,
            'sidebar_indent' => true,
            'label' => 'Categories',
            'description' => 'Taxonomy for quest listings.',
            'model' => QuestCategory::class,
            'creatable' => true,
            'list_columns' => ['id', 'name', 'slug', 'parent_id', 'is_active', 'sort_order'],
            'search_columns' => ['name', 'slug'],
            'fields' => [
                'name' => $reqText(160),
                'slug' => $reqText(120),
                'description' => $text(2000),
                'parent_id' => $relation('quest_categories'),
                'sort_order' => $int,
                'is_active' => $bool,
            ],
            'create_fields' => ['name', 'slug', 'description', 'parent_id', 'sort_order', 'is_active'],
            'edit_fields' => ['name', 'slug', 'description', 'parent_id', 'sort_order', 'is_active'],
        ],

        'quests' => [
            'group' => 'Quests & commerce',
            'sidebar_section' => 'Quests',
            'sidebar_order' => 20,
            'sidebar_sort' => 1,
            'label' => 'Quests',
            'description' => 'Job listings and engagements — full lifecycle management.',
            'model' => Quest::class,
            'creatable' => true,
            'editable' => true,
            'list_columns' => ['id', 'reference_code', 'title', 'status', 'escrow_status', 'created_at'],
            'search_columns' => ['title', 'reference_code', 'slug'],
            'with' => ['client:id,name,email', 'freelancer:id,name,email'],
            'fields' => [
                'title' => $reqText(200),
                'description' => [...$textarea(20000), 'strip_html' => true],
                'status' => $enum(QuestStatus::class),
                'escrow_status' => $select([
                    'unfunded' => 'Unfunded',
                    'pending' => 'Pending',
                    'funded' => 'Funded',
                    'released' => 'Released',
                    'refunded' => 'Refunded',
                    'disputed' => 'Disputed',
                ]),
                'client_id' => $relation('users', 'name'),
                'freelancer_id' => $relation('users', 'name'),
                'quest_category_id' => $relation('quest_categories'),
                'state_id' => $relation('states'),
                'local_government_id' => $relation('local_governments'),
                'city' => $text(120),
                'visibility' => $enum(QuestVisibility::class),
                'budget_amount_minor' => $money,
                'max_offers' => $int,
                'scheduled_start_date' => $date,
                'estimated_completion_days' => $int,
                'estimated_delivery_date' => $date,
                'due_at' => $date,
                'project_type' => $enum(QuestProjectType::class),
                'team_size' => $enum(QuestTeamSize::class),
                'start_timing' => $enum(QuestStartTiming::class),
                'availability_need' => $enum(QuestAvailabilityNeed::class),
                'freelancer_location_pref' => $enum(QuestFreelancerLocationPref::class),
                'dispute_opened' => $bool,
                'escrow_funded_at' => $date,
                'completed_at' => $date,
            ],
            'create_fields' => ['client_id', 'title', 'description', 'status', 'visibility', 'budget_amount_minor', 'quest_category_id', 'state_id', 'local_government_id', 'city'],
            'edit_fields' => $questEditFields,
        ],

        'proposals' => [
            'group' => 'Quests & commerce',
            'sidebar_section' => 'Quests',
            'sidebar_order' => 20,
            'sidebar_sort' => 2,
            'label' => 'Proposals',
            'description' => 'Freelancer offers on quests — all fields manageable.',
            'model' => QuestOffer::class,
            'creatable' => true,
            'editable' => true,
            'list_columns' => ['id', 'quest_id', 'freelancer_id', 'status', 'quoted_amount_minor', 'created_at'],
            'with' => ['quest:id,title,reference_code', 'freelancer:id,name,email'],
            'fields' => [
                'quest_id' => [...$relation('quests', 'title'), 'rules' => 'required|integer|exists:quests,id'],
                'freelancer_id' => [...$relation('users', 'name'), 'rules' => 'required|integer|exists:users,id'],
                'status' => $select([
                    'draft' => 'Draft',
                    'submitted' => 'Submitted',
                    'shortlisted' => 'Shortlisted',
                    'accepted' => 'Accepted',
                    'declined' => 'Declined',
                    'withdrawn' => 'Withdrawn',
                ]),
                'pitch' => $textarea(5000),
                'scope_detail' => $textarea(5000),
                'warranty_terms' => $textarea(2000),
                'proposed_completion_date' => $date,
                'planned_start_date' => $date,
                'planned_finish_date' => $date,
                'estimated_duration_days' => $int,
                'corrections_included' => $bool,
                'corrections_rounds' => $int,
                'progress_report_frequency' => $text(64),
                'materials' => [...$json, 'type' => 'key_value'],
                'pricing_snapshot' => [...$json, 'type' => 'key_value'],
                'quoted_amount_minor' => $money,
            ],
            'create_fields' => ['quest_id', 'freelancer_id', 'status', 'pitch', 'quoted_amount_minor'],
            'edit_fields' => $proposalEditFields,
        ],

        'quest_files' => [
            'group' => 'Quests & commerce',
            'sidebar_visible' => false,
            'label' => 'Quest files',
            'description' => 'Attachments on quest listings.',
            'model' => QuestFile::class,
            'creatable' => false,
            'list_columns' => ['id', 'quest_id', 'original_name', 'mime_type', 'size_bytes'],
            'with' => ['quest:id,title'],
            'fields' => [
                'original_name' => $text(255),
                'sort_order' => $int,
            ],
            'create_fields' => [],
            'edit_fields' => ['original_name', 'sort_order'],
        ],

        'quest_bookmarks' => [
            'group' => 'Quests & commerce',
            'sidebar_section' => 'Quests',
            'sidebar_order' => 20,
            'sidebar_sort' => 5,
            'sidebar_indent' => true,
            'label' => 'Bookmarks',
            'description' => 'Saved quests by users.',
            'model' => QuestBookmark::class,
            'creatable' => true,
            'list_columns' => ['id', 'quest_id', 'user_id', 'created_at'],
            'with' => ['quest:id,title', 'user:id,email'],
            'fields' => [
                'quest_id' => [...$relation('quests', 'title'), 'rules' => 'required|integer|exists:quests,id'],
                'user_id' => [...$relation('users', 'name'), 'rules' => 'required|integer|exists:users,id'],
            ],
            'create_fields' => ['quest_id', 'user_id'],
            'edit_fields' => ['quest_id', 'user_id'],
        ],

        'quest_invites' => [
            'group' => 'Quests & commerce',
            'sidebar_section' => 'Quests',
            'sidebar_order' => 20,
            'sidebar_sort' => 6,
            'sidebar_indent' => true,
            'label' => 'Quest invites',
            'description' => 'Client invites to freelancers on a quest.',
            'model' => QuestFreelancerInvite::class,
            'creatable' => true,
            'list_columns' => ['id', 'quest_id', 'freelancer_id', 'created_at'],
            'with' => ['quest:id,title', 'freelancer:id,email'],
            'fields' => [
                'quest_id' => [...$relation('quests', 'title'), 'rules' => 'required|integer|exists:quests,id'],
                'freelancer_id' => [...$relation('users', 'name'), 'rules' => 'required|integer|exists:users,id'],
            ],
            'create_fields' => ['quest_id', 'freelancer_id'],
            'edit_fields' => ['quest_id', 'freelancer_id'],
        ],

        'portfolios' => [
            'group' => 'Portfolios & reviews',
            'sidebar_section' => 'Portfolios',
            'sidebar_order' => 30,
            'sidebar_sort' => 1,
            'label' => 'Portfolios',
            'description' => 'Freelancer portfolio projects.',
            'model' => Portfolio::class,
            'creatable' => true,
            'list_columns' => ['id', 'user_id', 'title', 'status', 'admin_hidden', 'published_at'],
            'search_columns' => ['title', 'slug'],
            'with' => ['user:id,name,email'],
            'fields' => [
                'user_id' => $relation('users', 'name'),
                'quest_id' => $relation('quests', 'title'),
                'category_id' => $relation('quest_categories'),
                'subcategory_id' => $relation('quest_categories'),
                'title' => $reqText(200),
                'status' => $enum(PortfolioStatus::class),
                'admin_hidden' => $bool,
                'description' => [...$textarea(5000), 'strip_html' => true],
                'project_cost_minor' => $money,
            ],
            'create_fields' => ['user_id', 'quest_id', 'category_id', 'subcategory_id', 'title', 'description', 'project_cost_minor', 'status', 'admin_hidden'],
            'edit_fields' => ['user_id', 'quest_id', 'category_id', 'subcategory_id', 'title', 'status', 'admin_hidden', 'description', 'project_cost_minor'],
        ],

        'portfolio_files' => [
            'group' => 'Portfolios & reviews',
            'sidebar_visible' => false,
            'label' => 'Portfolio files',
            'description' => 'Media attached to portfolios.',
            'model' => PortfolioFile::class,
            'creatable' => false,
            'list_columns' => ['id', 'portfolio_id', 'original_name', 'mime_type'],
            'with' => ['portfolio:id,title'],
            'fields' => [
                'original_name' => $text(255),
                'sort_order' => $int,
            ],
            'create_fields' => [],
            'edit_fields' => ['original_name', 'sort_order'],
        ],

        'reviews' => [
            'group' => 'Portfolios & reviews',
            'sidebar_section' => 'Reviews',
            'sidebar_order' => 30,
            'sidebar_sort' => 1,
            'label' => 'Reviews',
            'description' => 'Ratings between clients and freelancers.',
            'model' => Review::class,
            'creatable' => true,
            'list_columns' => ['id', 'quest_id', 'rating', 'status', 'created_at'],
            'search_columns' => ['title', 'comment'],
            'with' => ['reviewer:id,email', 'reviewee:id,email', 'quest:id,title'],
            'fields' => [
                'quest_id' => $relation('quests', 'title'),
                'reviewer_id' => $relation('users', 'name'),
                'reviewee_id' => $relation('users', 'name'),
                'reviewer_party' => $select(['client' => 'Client', 'freelancer' => 'Freelancer']),
                'review_type' => $enum(ReviewType::class),
                'rating' => ['type' => 'select', 'rules' => 'nullable|integer|min:1|max:5', 'options' => [
                    ['value' => 1, 'label' => '1 star'],
                    ['value' => 2, 'label' => '2 stars'],
                    ['value' => 3, 'label' => '3 stars'],
                    ['value' => 4, 'label' => '4 stars'],
                    ['value' => 5, 'label' => '5 stars'],
                ]],
                'status' => $enum(ReviewStatus::class),
                'title' => $text(200),
                'comment' => $textarea(5000),
                'tags' => [...$json, 'type' => 'key_value'],
            ],
            'create_fields' => ['quest_id', 'reviewer_id', 'reviewee_id', 'reviewer_party', 'review_type', 'rating', 'status', 'title', 'comment'],
            'edit_fields' => ['quest_id', 'reviewer_id', 'reviewee_id', 'reviewer_party', 'review_type', 'rating', 'status', 'title', 'comment', 'tags'],
        ],

        'review_attachments' => [
            'group' => 'Portfolios & reviews',
            'sidebar_visible' => false,
            'label' => 'Review files',
            'description' => 'Files attached to reviews.',
            'model' => ReviewAttachment::class,
            'creatable' => false,
            'list_columns' => ['id', 'review_id', 'original_name'],
            'with' => ['review:id,title'],
            'fields' => [
                'original_name' => $text(255),
            ],
            'create_fields' => [],
            'edit_fields' => ['original_name'],
        ],

        'conversation_threads' => [
            'group' => 'Messaging',
            'sidebar_section' => 'Conversations',
            'sidebar_order' => 40,
            'sidebar_sort' => 1,
            'label' => 'Threads',
            'description' => 'One row per client–freelancer conversation on a quest. Open a thread to read messages.',
            'model' => QuestConversationThread::class,
            'creatable' => false,
            'editable' => false,
            'list_columns' => ['id', 'quest_id', 'client_id', 'freelancer_id', 'messages_count', 'last_message_at'],
            'with' => ['quest:id,title', 'client:id,name,email', 'freelancer:id,name,email'],
            'fields' => [],
            'create_fields' => [],
            'edit_fields' => [],
            'actions' => ['view_thread'],
        ],

        'conversation_messages' => [
            'group' => 'Messaging',
            'sidebar_visible' => false,
            'label' => 'Messages',
            'description' => 'Messages inside quest threads (open from thread view).',
            'model' => QuestConversationMessage::class,
            'creatable' => false,
            'list_columns' => ['id', 'quest_conversation_thread_id', 'user_id', 'created_at'],
            'search_columns' => ['body'],
            'with' => ['user:id,email', 'thread:id,quest_id'],
            'fields' => [
                'body' => $textarea(5000),
            ],
            'create_fields' => [],
            'edit_fields' => ['body'],
        ],

        'newsletter_subscribers' => [
            'group' => 'Marketing & moderation',
            'sidebar_section' => 'Marketing',
            'sidebar_order' => 60,
            'sidebar_sort' => 1,
            'label' => 'Newsletter',
            'description' => 'Newsletter email subscribers.',
            'model' => NewsletterSubscriber::class,
            'creatable' => true,
            'list_columns' => ['id', 'email', 'created_at'],
            'search_columns' => ['email'],
            'fields' => [
                'email' => $email,
            ],
            'create_fields' => ['email'],
            'edit_fields' => ['email'],
        ],

        'content_reports' => [
            'group' => 'Marketing & moderation',
            'sidebar_section' => 'Marketing',
            'sidebar_order' => 60,
            'sidebar_sort' => 2,
            'label' => 'Content reports',
            'description' => 'User-submitted moderation reports.',
            'model' => ContentReport::class,
            'creatable' => false,
            'list_columns' => ['id', 'user_id', 'reason', 'status', 'severity', 'created_at'],
            'with' => ['reporter:id,email'],
            'fields' => [
                'user_id' => [...$relation('users'), 'relation_name' => 'reporter'],
                'status' => $reqText(24),
                'severity' => $text(24),
            ],
            'create_fields' => [],
            'edit_fields' => ['status', 'severity'],
        ],
    ],
];
