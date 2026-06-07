<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\AdminPlatformSetting;
use App\Models\QuestCategory;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminSettingsRegistry
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function payload(): array
    {
        $stored = Schema::hasTable('admin_platform_settings')
            ? AdminPlatformSetting::query()->get()->keyBy('key')
            : collect();

        $sections = collect($this->definitions())->map(function (array $section) use ($stored) {
            $settings = collect($section['settings'])->map(function (array $setting) use ($section, $stored) {
                $record = $stored->get($setting['key']);
                $storedMinor = $record?->value['value'] ?? $setting['default'];
                $value = $setting['type'] === 'money'
                    ? $this->moneyMajorFromMinor((int) $storedMinor)
                    : $storedMinor;

                return [
                    ...$setting,
                    'value' => $setting['sensitive'] ?? false ? $this->mask($value) : $value,
                    'stored' => $record !== null,
                    'impact_count' => $this->impactCount($setting),
                    'current_note' => 'Current stored value: '.$this->displayValue($value, $setting['type']),
                ];
            })->values();

            return [
                ...Arr::except($section, 'settings'),
                'settings' => $settings,
            ];
        })->values();

        return [
            'sections' => $sections,
            'changelog' => $this->changelog(),
            'meta' => [
                'categories' => QuestCategory::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
                'generated_at' => now()->toIso8601String(),
                'platform_fee_disclosure' => \App\Support\PlatformFeeDisclosure::toArray(),
            ],
        ];
    }

    public function updateSection(string $sectionKey, array $values, Request $request): void
    {
        $section = collect($this->definitions())->firstWhere('key', $sectionKey);
        if (! $section) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        $settings = collect($section['settings'])->keyBy('key');
        $this->validateTierMonotonic($sectionKey, $values);

        foreach ($settings as $key => $definition) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if ($sectionKey === 'maintenance' && $key === 'maintenance.enabled') {
                $enabled = $this->castValue($values[$key], 'boolean');
                $maintenance = app(MaintenanceModeService::class);
                if ($enabled) {
                    $maintenance->enable(
                        $values['maintenance.message'] ?? null,
                        $values['maintenance.return_time'] ?? null,
                    );
                } else {
                    $maintenance->disable();
                }

                continue;
            }

            $newValue = $this->castValue($values[$key], $definition['type']);
            $record = AdminPlatformSetting::query()->firstOrNew(['key' => $key]);
            $oldValue = $record->exists ? ($record->value['value'] ?? null) : $definition['default'];

            $record->fill([
                'section' => $sectionKey,
                'value' => ['value' => $newValue],
                'is_sensitive' => (bool) ($definition['sensitive'] ?? false),
                'updated_by_admin_id' => $request->user()?->id,
            ])->save();

            \App\Support\PlatformSettings::forgetCache($key);

            if ($oldValue !== $newValue) {
                $this->logger->log(
                    actor: $request->user(),
                    action: 'admin.settings.updated',
                    subjectType: AdminPlatformSetting::class,
                    subjectId: $record->id,
                    properties: [
                        'section' => $sectionKey,
                        'key' => $key,
                        'label' => $definition['label'],
                        'from' => ($definition['sensitive'] ?? false) ? '[masked]' : $this->logValue($oldValue, $definition['type']),
                        'to' => ($definition['sensitive'] ?? false) ? '[masked]' : $this->logValue($newValue, $definition['type']),
                    ],
                    request: $request,
                );
            }
        }
    }

    public function definitions(): array
    {
        $tiers = range(0, 4);
        $tierFields = [];
        foreach ([
            'client_max_quest_value' => 'Client max Quest value',
            'client_max_escrow_funding' => 'Client max single escrow funding',
            'client_active_quests' => 'Client active Quests',
            'client_active_contracts' => 'Client active contracts',
            'freelancer_max_quest_value' => 'Freelancer max Quest value',
            'freelancer_active_proposals' => 'Freelancer active proposals',
            'freelancer_daily_proposals' => 'Freelancer proposals per day',
            'freelancer_weekly_proposals' => 'Freelancer proposals per week',
            'freelancer_single_payout' => 'Freelancer max single payout',
            'freelancer_weekly_payout' => 'Freelancer weekly payout total',
            'freelancer_monthly_payout' => 'Freelancer monthly payout total',
        ] as $prefix => $label) {
            foreach ($tiers as $tier) {
                $tierFields[] = $this->setting("verification.{$prefix}.tier_{$tier}", "{$label} · Tier {$tier}", 'number', 10000 * ($tier + 1), 'Tier-based limits must increase as verification increases.', ['group' => $prefix, 'tier' => $tier]);
            }
        }
        foreach (['featured_listing_access', 'quest_invitation_access', 'private_quest_access', 'featured_profile_access', 'quest_packages_access', 'priority_search_boost'] as $prefix) {
            foreach ($tiers as $tier) {
                $tierFields[] = $this->setting("verification.{$prefix}.tier_{$tier}", Str::headline($prefix)." · Tier {$tier}", 'boolean', $tier >= 2, 'Feature availability by verification tier.', ['group' => $prefix, 'tier' => $tier]);
            }
        }

        return [
            $this->section('general', 'General', 'Core platform identity, branding, locale and support contact settings.', [
                $this->setting('general.platform_name', 'Platform name', 'text', config('app.name', 'HustleSafe'), 'Displayed across the app, emails and browser titles.'),
                $this->setting('general.platform_tagline', 'Platform tagline', 'text', 'Safe freelance work, escrow protected.', 'Short phrase shown on landing pages and email headers.'),
                $this->setting('general.primary_domain', 'Primary domain', 'url', config('app.url'), 'Canonical domain used for generated links.'),
                $this->setting('general.accent_colour', 'Platform accent colour', 'color', '#0ea5e9', 'Updates brand preview colours and future CSS variables.'),
                $this->setting('general.default_language', 'Default language', 'select', 'en', 'Default platform interface language.', ['options' => ['en' => 'English']]),
                $this->setting('general.default_timezone', 'Default timezone', 'select', 'Africa/Lagos', 'Display timezone for admin-facing dates.', ['options' => ['Africa/Lagos' => 'Africa/Lagos (WAT)', 'UTC' => 'UTC']]),
                $this->setting('general.date_format', 'Date and time format', 'select', 'DD/MM/YYYY', 'Controls human date display format.', ['options' => ['DD/MM/YYYY' => 'DD/MM/YYYY', 'YYYY-MM-DD' => 'YYYY-MM-DD', 'Month DD YYYY' => 'Month DD YYYY']]),
                $this->setting('general.support_email', 'Support contact email', 'email', 'support@hustlesafe.com', 'Shown in footers and help pages.'),
                $this->setting('general.support_whatsapp', 'Support WhatsApp number', 'text', '+234', 'WhatsApp number with country code.'),
                $this->setting('general.cac_number', 'CAC registration number', 'text', '', 'Shown in legal and compliance documents.'),
                $this->setting('general.business_name', 'Registered business name', 'text', 'HustleSafe', 'Legal business name for documents.'),
                $this->setting('general.social_x', 'Twitter/X URL', 'url', '', 'Footer and email social link.'),
                $this->setting('general.social_instagram', 'Instagram URL', 'url', '', 'Footer and email social link.'),
                $this->setting('general.social_linkedin', 'LinkedIn URL', 'url', '', 'Footer and email social link.'),
            ]),
            $this->section('verification', 'Verification Tiers & Limits', 'Control what users can do at each trust tier.', $tierFields, 'matrix'),
            $this->section('financial', 'Financial & Escrow', 'Fees, escrow requirements, payout cadence, VAT and currency display.', [
                $this->setting('financial.platform_fee_percent', 'Platform fee (%) — single source of truth', 'number', 12, 'The only platform service fee rate. Shown on proposals, contracts, checkout, emails, legal pages, and all customer-facing copy. Applied to proposal subtotals (professional fee + materials + travel) and escrow release calculations.'),
                $this->setting('financial.high_value_quest_threshold_minor', 'High-value quest threshold (₦)', 'money', 100_000_000, 'Quests at or above this budget may require extra verification or category approval rules.'),
                $this->setting('financial.freelancer_fee_percent', 'Freelancer-side service fee % (legacy display)', 'number', 10, 'Deprecated for global billing — use Platform fee above. Category overrides may still reference this field.'),
                $this->setting('financial.minimum_escrow_minor', 'Minimum contract value requiring escrow (₦)', 'money', 0, 'Zero makes escrow mandatory for all contracts.'),
                $this->setting('financial.escrow_release_cooldown_hours', 'Minimum hours after escrow funding before release', 'number', 24, 'Clients cannot mark complete or release funds until this period passes after escrow is funded. Super admins may override from Financial Control.'),
                $this->setting('financial.high_value_release_authorization_minor', 'High-value release authorisation threshold (₦)', 'money', 100_000_000, 'Contracts at or above this amount (₦1,000,000) require super-admin authorisation before escrow can be released, even after the cooldown.'),
                $this->setting('financial.auto_release_hours', 'Escrow auto-release period', 'number', 72, 'Hours after agreed delivery date before escrow auto-releases if no dispute. Client emails at due day, +24h, and +36h.'),
                $this->setting('financial.minimum_payout_minor', 'Minimum payout threshold (₦)', 'money', 500000, 'Minimum pending earnings before payout.'),
                $this->setting('financial.payout_schedule', 'Payout processing schedule', 'select', 'daily', 'Instant, daily batch or twice-weekly processing.', ['options' => ['instant' => 'Instant', 'daily' => 'Daily batch', 'twice_weekly' => 'Twice weekly']]),
                $this->setting('financial.payout_batch_time', 'Payout batch time', 'time', '16:00', 'WAT batch payout processing time.'),
                $this->setting('financial.vat_enabled', 'VAT registration enabled', 'boolean', true, 'Adds VAT to service fees when enabled.'),
                $this->setting('financial.vat_percent', 'VAT percentage', 'number', 7.5, 'Current Nigerian VAT is 7.5%.'),
            ]),
            $this->section('notifications', 'Notifications & Communications', 'Notification defaults, mandatory alerts, senders, digests and retention.', [
                $this->setting('notifications.sender_name', 'Notification sender name', 'text', 'HustleSafe', 'From name for email and SMS.'),
                $this->setting('notifications.sms_sender_id', 'SMS sender ID', 'text', 'HustleSafe', 'Maximum 11 characters.'),
                $this->setting('notifications.admin_alert_recipients', 'Admin alert recipients', 'tags', '', 'Emails that receive critical platform alerts.'),
                $this->setting('notifications.daily_digest_enabled', 'Daily digest enabled', 'boolean', true, 'Sends Super Admin daily summary.'),
                $this->setting('notifications.digest_time', 'Digest send time', 'time', '08:00', 'Daily WAT digest time.'),
                $this->setting('notifications.unread_retention_days', 'Unread retention days', 'number', 180, 'Days before unread in-app notifications archive.'),
            ]),
            $this->section('security', 'Security & Access', 'Admin sessions, 2FA, passwords, IP allowlists and rate limits.', [
                $this->setting('security.admin_path', 'Admin panel URL path', 'text', '/admin', 'Changing requires deployment/server restart.', ['sensitive' => true]),
                $this->setting('security.admin_session_hours', 'Admin session lifetime', 'number', 8, 'Maximum hours an admin session stays alive.'),
                $this->setting('security.admin_inactivity_minutes', 'Admin inactivity timeout', 'number', 30, 'Minutes before idle admin session expires.'),
                $this->setting('security.failed_login_attempts', 'Maximum failed login attempts', 'number', 5, 'Temporary lockout threshold.'),
                $this->setting('security.admin_2fa_enforced', 'Enforce 2FA for Admins', 'boolean', true, 'Forces admins to enrol 2FA.'),
                $this->setting('security.password_min_length', 'Password minimum length', 'number', 12, 'Cannot be below 8.'),
                $this->setting('security.rate_limit_auth', 'Authenticated API requests/min', 'number', 120, 'Per-IP authenticated rate limit.'),
                $this->setting('security.rate_limit_guest', 'Guest API requests/min', 'number', 60, 'Per-IP unauthenticated rate limit.'),
            ]),
            $this->section('quests', 'Quest Settings', 'Quest content rules, attachments, expiry, proposal caps and approval gates.', [
                $this->setting('quests.title_max', 'Maximum Quest title length', 'number', 120, 'Enforced on Quest posting forms.'),
                $this->setting('quests.description_min', 'Minimum Quest description length', 'number', 150, 'Discourages vague low-quality Quests.'),
                $this->setting('quests.description_max', 'Maximum Quest description length', 'number', 5000, 'Upper description limit.'),
                $this->setting('quests.max_attachments', 'Maximum file attachments per Quest', 'number', 8, 'Files a client can attach.'),
                $this->setting('quests.max_file_mb', 'Maximum file size per attachment', 'number', 10, 'File size in MB.'),
                $this->setting('quests.accepted_file_types', 'Accepted file types', 'tags', 'pdf,doc,docx,png,jpg,webp,zip', 'Allowed Quest attachment extensions.'),
                $this->setting('quests.expiry_default_days', 'Quest expiry default (legacy)', 'number', 14, 'Deprecated alias — use proposal deadline default.'),
                $this->setting('quests.proposal_deadline_min_days', 'Proposal deadline minimum (days)', 'number', 1, 'Shortest listing window clients may choose when posting.'),
                $this->setting('quests.proposal_deadline_max_days', 'Proposal deadline maximum (days)', 'number', 60, 'Longest listing window clients may choose when posting.'),
                $this->setting('quests.proposal_deadline_default_days', 'Proposal deadline default (days)', 'number', 14, 'Pre-filled on Quest create — clients may change within min/max.'),
                $this->setting('quests.proposal_deadline_extension_max_days', 'Proposal deadline extension cap (days)', 'number', 14, 'Maximum extra days for the one-time client extension.'),
                $this->setting('quests.proposal_deadline_warning_hours', 'Proposal deadline warning (hours before)', 'number', 48, 'When to nudge the client to shortlist and award.'),
                $this->setting('quests.shortlist_max_per_quest', 'Shortlist cap per quest', 'number', 5, 'Maximum proposals a client may shortlist on one quest.'),
                $this->setting('quests.shortlist_award_nudge_days_after_deadline', 'Shortlist award nudge (days after deadline)', 'number', 7, 'Nudge clients with an active shortlist but no award this many days after listing expiry.'),
                $this->setting('quests.proposals_no_shortlist_review_days', 'Proposals without shortlist (days)', 'number', 5, 'Days with proposals but no shortlist before quest health and outreach signals fire.'),
                $this->setting('quests.max_proposals', 'Maximum proposals per Quest', 'number', 50, 'Zero means unlimited.'),
                $this->setting('quests.auto_close_on_hire', 'Auto-close Quest on hire', 'boolean', true, 'Stops new proposals after hire.'),
            ]),
            $this->section('proposals', 'Proposal Settings', 'Proposal cover letters, attachments, editing windows and bidding visibility.', [
                $this->setting('proposals.cover_letter_required', 'Proposal cover letter required', 'boolean', true, 'Require meaningful pitch text.'),
                $this->setting('proposals.cover_letter_min', 'Minimum cover letter length', 'number', 120, 'Reduces low-effort proposals.'),
                $this->setting('proposals.cover_letter_max', 'Maximum cover letter length', 'number', 3000, 'Upper pitch length.'),
                $this->setting('proposals.attachments_enabled', 'Allow proposal attachments', 'boolean', true, 'Freelancers can attach files.'),
                $this->setting('proposals.max_attachments', 'Maximum attachments per proposal', 'number', 5, 'Files per proposal.'),
                $this->setting('proposals.withdrawal_hours', 'Proposal withdrawal window', 'number', 24, 'Hours after submission.'),
                $this->setting('proposals.editing_hours', 'Proposal editing window', 'number', 12, 'Set zero to disable editing.'),
                $this->setting('proposals.visibility', 'Proposal count visibility', 'select', 'always', 'Controls competing proposal visibility.', ['options' => ['always' => 'Always visible', 'after_3' => 'Hidden until 3 proposals', 'hidden' => 'Always hidden']]),
            ]),
            $this->section('user_profile', 'User & Profile Settings', 'Profile completeness, public visibility, portfolio limits, account health and user-facing preferences.', [
                $this->setting('user_profile.minimum_profile_completion', 'Minimum profile completion %', 'number', 70, 'Minimum completion before freelancers can send proposals.'),
                $this->setting('user_profile.max_portfolio_items', 'Maximum portfolio items', 'number', 20, 'Portfolio items a freelancer can publish.'),
                $this->setting('user_profile.max_skills', 'Maximum skills per profile', 'number', 25, 'Prevents keyword stuffing.'),
                $this->setting('user_profile.public_profiles_enabled', 'Public profiles enabled', 'boolean', true, 'Allows profile pages to be visible outside logged-in areas.'),
                $this->setting('user_profile.profile_photo_required', 'Profile photo required for proposals', 'boolean', true, 'Requires a profile photo before proposal submission.'),
                $this->setting('user_profile.inactive_account_days', 'Inactive account review days', 'number', 180, 'Days of inactivity before account health review.'),
            ]),
            $this->section('payment_gateway', 'Payment Gateway', 'Paystack mode, keys, channels, webhook health and checkout timeout.', [
                $this->setting('payment.provider', 'Active payment provider', 'readonly', 'Paystack', 'Provider change requires deployment.'),
                $this->setting('payment.test_mode', 'Payment test mode', 'boolean', false, 'Shows persistent admin test-mode banner when enabled.'),
                $this->setting('payment.paystack_public_key', 'Paystack public key', 'password', '', 'Masked credential.', ['sensitive' => true]),
                $this->setting('payment.paystack_secret_key', 'Paystack secret key', 'password', '', 'Never shown in full.', ['sensitive' => true]),
                $this->setting('payment.webhook_secret', 'Paystack webhook secret', 'password', '', 'Webhook signature secret.', ['sensitive' => true]),
                $this->setting('payment.channels', 'Accepted payment methods', 'tags', 'card,bank_transfer,ussd', 'Enabled Paystack checkout channels.'),
                $this->setting('payment.transaction_timeout_minutes', 'Transaction timeout minutes', 'number', 30, 'Checkout session lifetime.'),
            ]),
            $this->section('identity_apis', 'Identity Verification APIs', 'KYC provider credentials, confidence thresholds and auto-approval rules.', [
                $this->setting('identity.active_provider', 'Active KYC provider', 'select', 'dojah', 'Provider used for verification.', ['options' => ['dojah' => 'Dojah', 'smile' => 'Smile Identity', 'mono' => 'Mono Identity']]),
                $this->setting('identity.api_key', 'Provider API key', 'password', '', 'Masked active provider key.', ['sensitive' => true]),
                $this->setting('identity.fallback_provider', 'Fallback provider', 'select', 'smile', 'Secondary provider.', ['options' => ['dojah' => 'Dojah', 'smile' => 'Smile Identity', 'mono' => 'Mono Identity']]),
                $this->setting('identity.nin_threshold', 'NIN confidence threshold', 'number', 85, 'Below threshold goes to review.'),
                $this->setting('identity.bvn_threshold', 'BVN confidence threshold', 'number', 85, 'Below threshold goes to review.'),
                $this->setting('identity.liveness_threshold', 'Liveness confidence threshold', 'number', 80, 'Selfie/document similarity threshold.'),
                $this->setting('identity.auto_approval', 'Auto-approval enabled', 'boolean', true, 'When off all cases queue for review.'),
            ]),
            $this->section('providers', 'Email & SMS Providers', 'Email/SMS providers, credentials, senders, health checks and balances.', [
                $this->setting('providers.email_provider', 'Active email provider', 'select', 'mailgun', 'Transactional email provider.', ['options' => ['mailgun' => 'Mailgun', 'sendgrid' => 'SendGrid']]),
                $this->setting('providers.email_api_key', 'Email provider API key', 'password', '', 'Masked email provider key.', ['sensitive' => true]),
                $this->setting('providers.from_name', 'From name', 'text', 'HustleSafe', 'Default email sender name.'),
                $this->setting('providers.from_email', 'From email', 'email', 'hello@hustlesafe.com', 'Must be on verified domain.'),
                $this->setting('providers.sms_provider', 'Active SMS provider', 'select', 'termii', 'SMS delivery provider.', ['options' => ['termii' => 'Termii', 'infobip' => 'Infobip']]),
                $this->setting('providers.sms_api_key', 'SMS provider API key', 'password', '', 'Masked SMS provider key.', ['sensitive' => true]),
                $this->setting('providers.sms_low_balance_threshold', 'SMS low balance threshold', 'number', 500, 'Alerts Super Admins below this balance.'),
            ]),
            $this->section('conversation_monitoring', 'Conversation Monitoring', 'Health score threshold, sanction thresholds, and suspension duration for flagged messaging.', [
                $this->setting('conversation_monitoring.health_risk_threshold', 'Conversation health risk threshold', 'number', 45, 'Users below this conversation health score are surfaced in Trust & Risk with Conversation Risk signal.'),
                $this->setting('conversation_monitoring.suspend_flag_threshold', 'Suspend after N flags', 'number', 3, 'Show suspend action when a user reaches this many confirmed/pending conversation flags.'),
                $this->setting('conversation_monitoring.ban_flag_threshold', 'Ban after N flags', 'number', 5, 'Show permanent ban action (Super Admin only) at this flag count.'),
                $this->setting('conversation_monitoring.suspend_duration_weeks', 'Default suspension duration (weeks)', 'number', 4, 'Weeks a user is suspended from the platform when sanctioned from conversation monitoring.'),
            ]),
            $this->section('review_moderation', 'Review & Rating Moderation', 'Default actions when amendment requests expire without a reviewer response.', [
                $this->setting('review_moderation.default_action.velocity_cluster', 'Velocity cluster — expired amendment', 'select', 'auto_remove', 'Action if reviewer does not amend.', ['options' => ['auto_publish' => 'Auto-publish', 'auto_remove' => 'Auto-remove']]),
                $this->setting('review_moderation.default_action.sentiment_mismatch', 'Sentiment mismatch — expired amendment', 'select', 'auto_publish', 'Action if reviewer does not amend.', ['options' => ['auto_publish' => 'Auto-publish', 'auto_remove' => 'Auto-remove']]),
                $this->setting('review_moderation.default_action.reciprocal_pair', 'Reciprocal pair — expired amendment', 'select', 'auto_remove', 'Action if reviewer does not amend.', ['options' => ['auto_publish' => 'Auto-publish', 'auto_remove' => 'Auto-remove']]),
                $this->setting('review_moderation.default_action.ip_cluster', 'IP cluster — expired amendment', 'select', 'auto_remove', 'Action if reviewer does not amend.', ['options' => ['auto_publish' => 'Auto-publish', 'auto_remove' => 'Auto-remove']]),
                $this->setting('review_moderation.default_action.blacklisted_keyword', 'Blacklisted keyword — expired amendment', 'select', 'auto_remove', 'Action if reviewer does not amend.', ['options' => ['auto_publish' => 'Auto-publish', 'auto_remove' => 'Auto-remove']]),
            ]),
            $this->section('trust_risk', 'Trust & Risk Monitoring', 'Composite risk score tier boundaries and automatic Risk Queue threshold.', [
                $this->setting('trust_risk.tier_low_max', 'Low risk maximum (0–N)', 'number', 39, 'Scores at or below this value are Low tier (green).'),
                $this->setting('trust_risk.tier_medium_max', 'Medium risk maximum', 'number', 69, 'Scores above Low max and at or below this are Medium (amber).'),
                $this->setting('trust_risk.tier_high_max', 'High risk maximum', 'number', 84, 'Scores above Medium max and at or below this are High (orange). Critical is above High max.'),
                $this->setting('trust_risk.monitoring_queue_min_score', 'Risk Queue auto-enrol threshold', 'number', 40, 'Users at or above this composite score appear in the Staff Risk Queue.'),
            ]),
            $this->section('moderation', 'Content & Moderation', 'Keywords, external links, budget anomaly thresholds and image/review moderation.', [
                $this->setting('moderation.prohibited_keywords', 'Prohibited keywords', 'tags', 'whatsapp,telegram,pay outside', 'Words/phrases that trigger flags.'),
                $this->setting('moderation.link_whitelist', 'External link whitelist', 'tags', 'linkedin.com,github.com,behance.net,dribbble.com,figma.com', 'Allowed domains.'),
                $this->setting('moderation.auto_flag_new_accounts', 'Auto-flag new account content', 'boolean', true, 'Moderate content from young accounts.'),
                $this->setting('moderation.budget_low_percentile', 'Budget low percentile flag', 'number', 10, 'Below this percentile flags anomaly.'),
                $this->setting('moderation.budget_high_percentile', 'Budget high percentile flag', 'number', 99, 'Above this percentile flags anomaly.'),
                $this->setting('moderation.image_provider', 'Image moderation provider', 'select', 'cloudinary', 'Image moderation provider.', ['options' => ['cloudinary' => 'Cloudinary', 'rekognition' => 'Amazon Rekognition', 'azure' => 'Azure Content Moderator']]),
                $this->setting('moderation.image_flag_threshold', 'Image flag threshold', 'number', 70, 'Confidence score to flag.'),
                $this->setting('moderation.image_reject_threshold', 'Image reject threshold', 'number', 95, 'Confidence score to block pending review.'),
            ]),
            $this->section('freelancer_pro', 'Freelancer Pro Membership', 'Self-service Pro subscription pricing, portfolio limits, and verification SLA. Pro never bypasses trust tiers or job value caps. Monthly proposal counts are configured per verification tier in Verification Engine → Limits.', [
                $this->setting('freelancer_pro.monthly_price_minor', 'Pro monthly price (₦)', 'money', 1_000_000, 'Monthly Pro subscription price.'),
                $this->setting('freelancer_pro.annual_price_minor', 'Pro annual price (₦)', 'money', 10_000_000, 'Annual Pro subscription price (discounted vs 12× monthly).'),
                $this->setting('freelancer_pro.free_portfolio_items', 'Free tier portfolio items', 'number', 5, 'Maximum portfolio items for non-Pro freelancers. Pro members have unlimited uploads.'),
                $this->setting('freelancer_pro.kyc_sla_hours', 'Pro KYC review SLA (hours)', 'number', 24, 'Maximum turnaround for Pro freelancer verification reviews.'),
                $this->setting('freelancer_pro.standard_kyc_sla_hours', 'Standard KYC review SLA (hours)', 'number', 72, 'Turnaround shown to free-tier freelancers (48–72 hours typical).'),
            ]),
            $this->section('quest_boosts', 'Quest Boost Pricing', 'Client-paid quest boost tier prices in naira. Applies to new boosts only.', [
                $this->setting('quest_boosts.price_3_day_minor', '3-day boost price (₦)', 'money', 800_000, '72-hour boost price.'),
                $this->setting('quest_boosts.price_7_day_minor', '7-day boost price (₦)', 'money', 1_500_000, '7-day boost price.'),
                $this->setting('quest_boosts.price_14_day_minor', '14-day boost price (₦)', 'money', 2_800_000, '14-day boost price.'),
                $this->setting('quest_boosts.price_30_day_minor', '30-day boost price (₦)', 'money', 5_200_000, '30-day boost price.'),
            ]),
            $this->section('promotions', 'Featured Listings & Promotions', 'Boost tiers, coupons, referral programme and reward controls.', [
                $this->setting('promotions.max_featured_per_client', 'Maximum concurrent featured Quests per client', 'number', 3, 'Prevents one client dominating placement.'),
                $this->setting('promotions.auto_renew_featured', 'Featured listing auto-renewal', 'boolean', false, 'Renew boosts automatically.'),
                $this->setting('promotions.max_coupon_discount_percent', 'Maximum coupon discount %', 'number', 40, 'Hard cap on coupon generosity.'),
                $this->setting('promotions.max_coupon_validity_days', 'Maximum coupon validity days', 'number', 90, 'Longest coupon lifetime.'),
                $this->setting('promotions.coupons_combinable', 'Coupons can combine with other promos', 'boolean', false, 'Controls promo stacking risk.'),
            ]),
            $this->section('referrals', 'Referral Programme', 'Referral programme eligibility, reward types, qualifying events, expiry and abuse limits.', [
                $this->setting('referrals.enabled', 'Referral programme enabled', 'boolean', true, 'Master referral toggle.'),
                $this->setting('referrals.client_reward_minor', 'Client referral reward amount (₦)', 'money', 500000, 'Reward after first funded Quest.'),
                $this->setting('referrals.client_reward_type', 'Client referral reward type', 'select', 'wallet_credit', 'Reward format for referred clients.', ['options' => ['wallet_credit' => 'Wallet credit', 'cash_payout' => 'Cash payout', 'coupon' => 'Coupon']]),
                $this->setting('referrals.freelancer_reward_minor', 'Freelancer referral reward amount (₦)', 'money', 500000, 'Reward after first milestone payout.'),
                $this->setting('referrals.freelancer_reward_type', 'Freelancer referral reward type', 'select', 'wallet_credit', 'Reward format for referred freelancers.', ['options' => ['wallet_credit' => 'Wallet credit', 'cash_payout' => 'Cash payout', 'coupon' => 'Coupon']]),
                $this->setting('referrals.qualifying_event', 'Referral qualifying event', 'select', 'first_transaction', 'Event that triggers reward.', ['options' => ['first_login' => 'First login', 'profile_completion' => 'First profile completion', 'first_transaction' => 'First transaction']]),
                $this->setting('referrals.reward_expiry_days', 'Referral reward expiry days', 'number', 90, 'Wallet credit validity period.'),
            ]),
            $this->section('sla', 'SLA Engine', 'Human decision deadlines for disputes, KYC, escrow, support, moderation, and other operational reviews. Clocks start automatically and escalate to Super Admin when breached.', [
                $this->setting('sla.dispute_resolution.working_days', 'Dispute resolution (working days)', 'number', 10, 'Target time for staff to rule on escalated disputes.'),
                $this->setting('sla.kyc_verification.hours', 'KYC verification review (hours)', 'number', 24, 'Manual identity and credential review turnaround.'),
                $this->setting('sla.escrow_release_appeal.hours', 'Escrow release authorisation (hours)', 'number', 48, 'High-value or held escrow release decisions.'),
                $this->setting('sla.support_ticket_response.working_days', 'Support ticket response (working days)', 'number', 10, 'Managed support ticket resolution target.'),
                $this->setting('sla.quest_moderation_review.hours', 'Quest moderation review (hours)', 'number', 48, 'Quest retrospective moderation queue.'),
                $this->setting('sla.proposal_moderation_review.hours', 'Proposal moderation review (hours)', 'number', 48, 'Proposal retrospective moderation queue.'),
                $this->setting('sla.content_moderation.hours', 'Content moderation (hours)', 'number', 24, 'Flagged content and policy case review.'),
                $this->setting('sla.portfolio_review.working_days', 'Portfolio review (working days)', 'number', 5, 'Freelancer portfolio approval turnaround.'),
                $this->setting('sla.review_moderation.hours', 'Review & rating moderation (hours)', 'number', 72, 'Review authenticity and amendment queue.'),
                $this->setting('sla.financial_review.hours', 'Financial review (hours)', 'number', 48, 'Treasury and payout exception review.'),
                $this->setting('sla.onboarding_quality_review.working_days', 'Onboarding quality review (working days)', 'number', 3, 'Flagged profile and onboarding QC.'),
                $this->setting('sla.sanction_appeal.working_days', 'Sanction appeal review (working days)', 'number', 5, 'User sanction appeal decisions.'),
            ]),
            $this->section('disputes', 'Dispute & Resolution', 'Dispute windows, escalation deadlines, fees, appeals and suspension thresholds.', [
                $this->setting('disputes.minimum_formal_value_minor', 'Minimum formal dispute value (₦)', 'money', 5000000, 'Below this uses simplified process.'),
                $this->setting('disputes.window_days_after_completion', 'Dispute window after completion', 'number', 7, 'Days after completion to raise dispute.'),
                $this->setting('disputes.tier1_hours', 'Tier 1 self-resolution window', 'number', 48, 'Hours before escalation.'),
                $this->setting('disputes.tier2_hours', 'Tier 2 AI mediation window', 'number', 72, 'Hours before admin review.'),
                $this->setting('disputes.admin_review_days', 'Tier 3 admin review deadline', 'number', 5, 'Days for ruling.'),
                $this->setting('disputes.auto_escalation', 'Auto-escalation enabled', 'boolean', true, 'Escalate on missed response.'),
                $this->setting('disputes.resolution_fee_percent', 'Dispute resolution fee %', 'number', 5, 'Charged to losing party.'),
                $this->setting('disputes.appeal_window_days', 'Appeal window days', 'number', 3, 'Days after ruling.'),
            ]),
            $this->section('analytics', 'Analytics & Tracking', 'GA4, GTM, pixels, error tracking and analytics retention.', [
                $this->setting('analytics.ga4_id', 'Google Analytics measurement ID', 'text', '', 'GA4 tracking ID.'),
                $this->setting('analytics.gtm_id', 'Google Tag Manager container ID', 'text', '', 'GTM container ID.'),
                $this->setting('analytics.facebook_pixel_id', 'Facebook Pixel ID', 'text', '', 'Ad retargeting pixel.'),
                $this->setting('analytics.hotjar_id', 'Hotjar site ID', 'text', '', 'Session recording ID.'),
                $this->setting('analytics.sentry_dsn', 'Sentry DSN', 'password', '', 'Error tracking DSN.', ['sensitive' => true]),
                $this->setting('analytics.retention_months', 'Internal analytics retention months', 'number', 24, 'Raw analytics retention before summarisation.'),
                $this->setting('analytics.dashboard_default_range', 'Dashboard default date range', 'select', 'last_30_days', 'Default admin dashboard range.', ['options' => ['today' => 'Today', 'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days', 'last_90_days' => 'Last 90 days']]),
            ]),
            $this->section('maintenance', 'Maintenance & System', 'Maintenance mode, queues, cache, scheduled tasks and application version.', [
                $this->setting('maintenance.enabled', 'Maintenance mode enabled', 'boolean', false, 'Shows maintenance page to non-admin users.'),
                $this->setting('maintenance.message', 'Maintenance message', 'text', 'We are improving HustleSafe. Please check back soon.', 'Message shown to users.'),
                $this->setting('maintenance.return_time', 'Estimated return time', 'datetime-local', '', 'Shown to users during maintenance.'),
                $this->setting('maintenance.queue_status', 'Queue worker status', 'readonly', 'Monitor via supervisor / queue dashboard', 'Read-only operational reference.'),
                $this->setting('maintenance.application_version', 'Application version', 'readonly', config('app.version', 'local'), 'Deployment/version reference.'),
            ]),
            $this->section('legal', 'Legal & Compliance', 'Terms, privacy, cookie policy, AUP, NDPR tools and consent history.', [
                $this->setting('legal.terms_effective_date', 'Terms effective date', 'date', now()->toDateString(), 'Future effective date prompts users on login.'),
                $this->setting('legal.privacy_effective_date', 'Privacy policy effective date', 'date', now()->toDateString(), 'Privacy version effective date.'),
                $this->setting('legal.cookie_effective_date', 'Cookie policy effective date', 'date', now()->toDateString(), 'Cookie version effective date.'),
                $this->setting('legal.aup_effective_date', 'Acceptable use policy effective date', 'date', now()->toDateString(), 'AUP version effective date.'),
                $this->setting('legal.ndpr_contact_email', 'NDPR contact email', 'email', 'privacy@hustlesafe.com', 'Data protection request contact.'),
            ]),
            $this->section('danger', 'Danger Zone', 'Irreversible platform-wide actions guarded by Super Admin confirmation.', [
                $this->setting('danger.flush_sessions_phrase', 'Flush all sessions confirmation phrase', 'readonly', 'FLUSH ALL SESSIONS', 'Required phrase for session flush.'),
                $this->setting('danger.reset_2fa_phrase', 'Reset admin 2FA confirmation phrase', 'readonly', 'RESET 2FA', 'Required phrase for 2FA reset.'),
                $this->setting('danger.purge_data_phrase', 'Purge expired data phrase', 'readonly', 'PURGE EXPIRED DATA', 'Required phrase for retention purge.'),
                $this->setting('danger.full_export_phrase', 'Full platform export phrase', 'readonly', 'EXPORT FULL PLATFORM DATA', 'Required phrase for full encrypted export.'),
            ], 'danger'),
        ];
    }

    private function section(string $key, string $label, string $description, array $settings, string $layout = 'standard'): array
    {
        return compact('key', 'label', 'description', 'settings', 'layout');
    }

    private function setting(string $key, string $label, string $type, mixed $default, string $description, array $extra = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'default' => $default,
            'description' => $description,
            'options' => $extra['options'] ?? [],
            'sensitive' => (bool) ($extra['sensitive'] ?? false),
            'group' => $extra['group'] ?? null,
            'tier' => $extra['tier'] ?? null,
        ];
    }

    private function validateTierMonotonic(string $sectionKey, array $values): void
    {
        if ($sectionKey === 'trust_risk') {
            $low = (int) ($values['trust_risk.tier_low_max'] ?? 39);
            $medium = (int) ($values['trust_risk.tier_medium_max'] ?? 69);
            $high = (int) ($values['trust_risk.tier_high_max'] ?? 84);

            if ($low >= $medium || $medium >= $high || $high >= 100) {
                throw ValidationException::withMessages([
                    'trust_risk.tier_medium_max' => 'Risk tiers must increase: low max < medium max < high max < 100.',
                ]);
            }

            return;
        }

        if ($sectionKey !== 'verification') {
            return;
        }

        $groups = collect($this->definitions())
            ->firstWhere('key', 'verification')['settings'];
        foreach (collect($groups)->whereNotNull('group')->groupBy('group') as $group => $settings) {
            $previous = null;
            foreach ($settings->sortBy('tier') as $setting) {
                if ($setting['type'] !== 'number') {
                    continue;
                }
                $value = (float) ($values[$setting['key']] ?? $setting['default']);
                if ($previous !== null && $value < $previous) {
                    throw ValidationException::withMessages([$setting['key'] => 'Tier limits must not decrease as verification tier increases.']);
                }
                $previous = $value;
            }
        }
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : 0,
            'money' => is_numeric($value) ? max(0, (int) round(((float) $value) * 100)) : 0,
            'boolean' => $this->castBoolean($value),
            default => is_array($value) ? $value : (string) $value,
        };
    }

    private function castBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function mask(mixed $value): string
    {
        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        return Str::substr($text, 0, 4).str_repeat('•', max(4, strlen($text) - 8)).Str::substr($text, -4);
    }

    private function displayValue(mixed $value, string $type = 'text'): string
    {
        if (is_bool($value)) {
            return $value ? 'Enabled' : 'Disabled';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if ($value === '' || $value === null) {
            return 'Not set';
        }
        if ($type === 'money') {
            return '₦'.number_format((float) $value, 0);
        }

        return (string) $value;
    }

    private function moneyMajorFromMinor(int $minor): float
    {
        return round($minor / 100, 2);
    }

    private function logValue(mixed $value, string $type): mixed
    {
        if ($type !== 'money' || ! is_numeric($value)) {
            return $value;
        }

        return $this->moneyMajorFromMinor((int) $value);
    }

    private function impactCount(array $setting): int
    {
        if (! str_starts_with($setting['key'], 'verification.') || $setting['tier'] === null) {
            return 0;
        }

        if (! Schema::hasColumn('users', 'kyc_tier')) {
            return 0;
        }

        return User::query()->where('kyc_tier', (int) $setting['tier'])->count();
    }

    private function changelog(): array
    {
        return AdminActivityLog::query()
            ->with('actor:id,name,email')
            ->where('action', 'admin.settings.updated')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor?->name ?? 'System',
                'key' => $log->properties['key'] ?? '',
                'label' => $log->properties['label'] ?? '',
                'from' => $log->properties['from'] ?? null,
                'to' => $log->properties['to'] ?? null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
