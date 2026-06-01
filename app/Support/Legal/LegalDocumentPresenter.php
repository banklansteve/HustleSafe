<?php

namespace App\Support\Legal;

use App\Support\EscrowAutoReleasePolicy;
use App\Support\PlatformSettings;

final class LegalDocumentPresenter
{
    public const LAST_UPDATED = '29 May 2026';

    /**
     * @return array<string, mixed>
     */
    public function build(string $key): array
    {
        $document = match ($key) {
            'terms' => $this->terms(),
            'privacy' => $this->privacy(),
            'escrow' => $this->escrow(),
            'dispute' => $this->dispute(),
            default => abort(404),
        };

        return array_merge($document, [
            'key' => $key,
            'last_updated' => self::LAST_UPDATED,
            'related_policies' => $this->relatedPolicies($key),
            'pdf_url' => route("legal.{$key}.pdf"),
        ]);
    }

    /**
     * @return list<array{label: string, href: string, description: string}>
     */
    private function relatedPolicies(string $current): array
    {
        $all = [
            ['key' => 'terms', 'label' => 'Terms of Service', 'description' => 'Master agreement for every user'],
            ['key' => 'privacy', 'label' => 'Privacy Policy', 'description' => 'How we handle your personal data'],
            ['key' => 'escrow', 'label' => 'Escrow Policy', 'description' => 'How funds are held and released'],
            ['key' => 'dispute', 'label' => 'Dispute Policy', 'description' => 'How disagreements are resolved'],
        ];

        return collect($all)
            ->reject(fn (array $row) => $row['key'] === $current)
            ->map(fn (array $row) => [
                'label' => $row['label'],
                'href' => route("legal.{$row['key']}"),
                'description' => $row['description'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function vars(): array
    {
        $fee = PlatformSettings::platformFeePercent();
        $autoHours = EscrowAutoReleasePolicy::releaseHours();
        $cooldown = PlatformSettings::escrowReleaseCooldownHours();
        $minDispute = (int) config('disputes.minimum_disputed_amount_minor', 500_000);
        $selfHours = (int) config('disputes.self_resolution_response_hours', 48);
        $rulingHours = (int) config('disputes.formal_no_response_ruling_hours', 72);
        $completionWindow = (int) config('disputes.max_days_after_completion_to_open', 14);
        $resolutionFee = (float) config('disputes.platform_resolution_fee_percent', 2.0);

        return [
            'platform_name' => config('app.name', 'HustleSafe'),
            'platform_fee' => rtrim(rtrim(number_format($fee, 1), '0'), '.'),
            'auto_release_hours' => (string) $autoHours,
            'escrow_cooldown_hours' => (string) $cooldown,
            'min_dispute_amount' => '₦'.number_format($minDispute / 100, 0),
            'self_resolution_hours' => (string) $selfHours,
            'formal_ruling_hours' => (string) $rulingHours,
            'dispute_completion_days' => (string) $completionWindow,
            'dispute_resolution_fee' => rtrim(rtrim(number_format($resolutionFee, 1), '0'), '.'),
            'terms_url' => route('legal.terms'),
            'privacy_url' => route('legal.privacy'),
            'escrow_url' => route('legal.escrow'),
            'dispute_url' => route('legal.dispute'),
            'help_url' => route('help.index'),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function fill(array $lines): array
    {
        $replace = [];
        foreach ($this->vars() as $key => $value) {
            $replace[':'.$key] = (string) $value;
        }

        return array_map(fn (string $line) => strtr($line, $replace), $lines);
    }

    /**
     * @param  list<array{id: string, title: string, paragraphs?: list<string>, bullets?: list<string>}>  $sections
     * @return list<array<string, mixed>>
     */
    private function fillSections(array $sections): array
    {
        return array_map(function (array $section): array {
            if (isset($section['paragraphs'])) {
                $section['paragraphs'] = $this->fill($section['paragraphs']);
            }
            if (isset($section['bullets'])) {
                $section['bullets'] = $this->fill($section['bullets']);
            }

            return $section;
        }, $sections);
    }

    /**
     * @return array<string, mixed>
     */
    private function terms(): array
    {
        return [
            'title' => 'Terms of Service',
            'tagline' => 'The agreement between you and HustleSafe',
            'summary' => $this->fill([
                'HustleSafe is a Nigerian marketplace that connects clients (“Project Sponsors”) and freelancers (“Safe Hustlers”) with escrow-protected payments.',
                'You must keep payments and project communication on the platform, provide honest information, and follow our Escrow Policy and Dispute Policy.',
                'Clients fund escrow before work begins. Freelancers deliver work as agreed in the contract. Funds release when the client marks the job complete or under our automatic release rules.',
                'We may update these terms. If you continue using HustleSafe after we publish changes, you accept the updated terms.',
            ]),
            'sections' => $this->fillSections([
                [
                    'id' => 'acceptance',
                    'title' => '1. Accepting these terms',
                    'paragraphs' => [
                        'By creating an account, posting a quest, submitting a proposal, funding escrow, or using any part of :platform_name, you agree to these Terms of Service, our Privacy Policy, Escrow Policy, and Dispute Policy.',
                        'If you do not agree, please do not use the platform.',
                    ],
                ],
                [
                    'id' => 'accounts',
                    'title' => '2. Your account',
                    'paragraphs' => [
                        'You must provide accurate registration details and keep your login secure. You are responsible for activity on your account.',
                        'We offer identity verification (KYC) to increase trust and unlock higher limits. You agree to submit truthful documents when asked and not to impersonate another person.',
                        'We may suspend or close accounts that break these terms, abuse other users, attempt fraud, or bypass platform fees.',
                    ],
                ],
                [
                    'id' => 'roles',
                    'title' => '3. Clients and freelancers',
                    'bullets' => [
                        'Clients (“Project Sponsors”) post quests, review proposals, award work, fund escrow, communicate on-platform, mark delivery complete, or open disputes when needed.',
                        'Freelancers (“Safe Hustlers”) submit honest proposals, deliver agreed work, use the quest thread for updates, request delivery extensions when genuinely needed, and respect contract deadlines.',
                        'Both parties must treat each other professionally and keep material project discussion inside HustleSafe.',
                    ],
                ],
                [
                    'id' => 'payments',
                    'title' => '4. Payments and fees',
                    'paragraphs' => [
                        'All project payments must go through HustleSafe escrow unless we give written permission otherwise. Off-platform payment to avoid fees is prohibited and may lead to account action.',
                        'Clients pay the quoted amount into escrow (including applicable platform and statutory charges shown before checkout). Our current platform service fee is approximately :platform_fee% of the contract value unless a different rate is shown at award.',
                        'Payments are processed through Paystack, our licensed payment partner. By funding escrow you also accept Paystack’s applicable terms for that transaction.',
                        'Freelancers receive their net payout after escrow release, minus the platform fee shown in the contract.',
                    ],
                ],
                [
                    'id' => 'contracts',
                    'title' => '5. Contracts and delivery',
                    'paragraphs' => [
                        'When a client awards a proposal and both parties confirm, we generate a digital contract that records scope, price, delivery date, deliverables, and platform rules.',
                        'The contract links to these Terms, the Escrow Policy, and the Dispute Policy. Contract amendments and delivery date extensions follow the in-product flows and require appropriate consent.',
                        'Freelancers may request up to two delivery extensions per contract through the formal extension process. Clients have a limited time to accept, decline, or counter-propose.',
                    ],
                ],
                [
                    'id' => 'escrow-summary',
                    'title' => '6. Escrow and completion (summary)',
                    'paragraphs' => [
                        'Escrow holds the client’s payment safely until the job is properly completed. Read the full Escrow Policy at :escrow_url for step-by-step detail.',
                        'The client marks the job complete when satisfied. If the client takes no action after the agreed delivery date, we send review reminders and may automatically release escrow after :auto_release_hours hours unless a dispute is open.',
                        'Opening a dispute pauses automatic release and starts the process described in our Dispute Policy.',
                    ],
                ],
                [
                    'id' => 'conduct',
                    'title' => '7. Acceptable use',
                    'bullets' => [
                        'No harassment, hate speech, scams, or illegal services.',
                        'No sharing personal payment details in messages to bypass escrow.',
                        'No fake reviews, duplicate accounts to manipulate ratings, or misrepresentation of skills.',
                        'No uploading malware or content that infringes someone else’s rights.',
                        'We monitor messages for safety patterns and may redact or flag content that breaks marketplace rules.',
                    ],
                ],
                [
                    'id' => 'disputes-summary',
                    'title' => '8. Disputes (summary)',
                    'paragraphs' => [
                        'If something goes wrong, either party may open a structured dispute while escrow is active or within :dispute_completion_days days after completion (subject to minimum contract value rules).',
                        'Disputes run in timed stages so cases do not stall. Full rules are in our Dispute Policy at :dispute_url.',
                    ],
                ],
                [
                    'id' => 'privacy',
                    'title' => '9. Privacy',
                    'paragraphs' => [
                        'We process personal data under the Nigeria Data Protection Regulation (NDPR) and our Privacy Policy at :privacy_url.',
                        'That policy explains what we collect, why, how long we keep it, and how to request access or deletion.',
                    ],
                ],
                [
                    'id' => 'changes',
                    'title' => '10. Changes and contact',
                    'paragraphs' => [
                        'We may update these terms to reflect product changes or legal requirements. We will update the “Last updated” date and, where appropriate, notify you in the app or by email.',
                        'Continued use after changes take effect means you accept the updated terms.',
                        'Questions about these terms can be sent through our Help centre at :help_url or via the in-app support chat.',
                    ],
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privacy(): array
    {
        return [
            'title' => 'Privacy Policy',
            'tagline' => 'How HustleSafe handles your personal data',
            'summary' => $this->fill([
                'We collect only what we need to run the marketplace, verify identities, process escrow payments, prevent fraud, and support you.',
                'We do not sell your personal data. We share data with trusted service providers (such as Paystack for payments) under strict agreements.',
                'You have rights under Nigerian law (NDPR) to access, correct, or request deletion of your data, subject to legal retention needs.',
                'Have a Nigerian tech-focused lawyer review this policy before launch alongside our Terms of Service.',
            ]),
            'sections' => $this->fillSections([
                [
                    'id' => 'scope',
                    'title' => '1. Who this policy covers',
                    'paragraphs' => [
                        'This Privacy Policy applies to everyone who visits :platform_name, creates an account, posts quests, submits proposals, funds escrow, or contacts support.',
                        'It should be read together with our Terms of Service at :terms_url.',
                    ],
                ],
                [
                    'id' => 'controller',
                    'title' => '2. Data controller',
                    'paragraphs' => [
                        'HustleSafe operates the platform and decides how your personal data is used for marketplace services.',
                        'For privacy requests, contact us through :help_url or the support chat inside your account.',
                    ],
                ],
                [
                    'id' => 'collect',
                    'title' => '3. Data we collect',
                    'bullets' => [
                        'Account data: name, email, phone, password hash, username, profile photo, location (state/LGA/city), profession, and account type.',
                        'Identity verification (KYC): government ID details, verification documents, and review outcomes when you choose to verify.',
                        'Transaction data: escrow amounts, wallet activity, payout bank details, Paystack references, and contract records.',
                        'Project data: quests, proposals, messages, deliverables, contracts, disputes, and reviews.',
                        'Technical data: IP address, browser type, device information, login history, and cookies that keep you signed in.',
                        'Support data: messages you send to customer support and attachments you upload.',
                    ],
                ],
                [
                    'id' => 'why',
                    'title' => '4. Why we use your data',
                    'bullets' => [
                        'To create and secure your account.',
                        'To match clients with freelancers and display public profiles you choose to publish.',
                        'To hold and release escrow payments and comply with financial record-keeping duties.',
                        'To verify identity and reduce fraud.',
                        'To run disputes, moderation, and trust & safety reviews.',
                        'To send service emails and in-app alerts (for example escrow reminders and contract updates).',
                        'To improve reliability and fix bugs.',
                    ],
                ],
                [
                    'id' => 'sharing',
                    'title' => '5. Who we share data with',
                    'paragraphs' => [
                        'We do not sell your personal information. We share data only when needed to run the service:',
                    ],
                    'bullets' => [
                        'Paystack — payment collection, escrow funding, and freelancer payouts.',
                        'Cloud and file hosts — secure storage for profile images, portfolio files, and verification uploads.',
                        'Email and notification providers — to deliver account and transaction messages you expect.',
                        'Professional advisers or regulators — when required by law, court order, or to protect rights and safety.',
                        'Other users — only what you deliberately make public (such as your freelancer profile, portfolio, or quest listing).',
                    ],
                ],
                [
                    'id' => 'retention',
                    'title' => '6. How long we keep data',
                    'paragraphs' => [
                        'We keep account and transaction records while your account is active and for a reasonable period afterward to meet legal, tax, and dispute needs.',
                        'Contract, escrow, and dispute records may be kept longer because they evidence financial and legal transactions.',
                        'You may request deletion of your account; we will remove or anonymise data where we are not required to keep it.',
                    ],
                ],
                [
                    'id' => 'rights',
                    'title' => '7. Your rights (NDPR)',
                    'bullets' => [
                        'Request a copy of personal data we hold about you.',
                        'Ask us to correct inaccurate information.',
                        'Ask us to delete data where we have no lawful reason to keep it.',
                        'Object to certain processing or ask us to restrict use in specific cases.',
                        'Withdraw consent where processing is based on consent (without affecting past lawful processing).',
                    ],
                    'paragraphs' => [
                        'To exercise these rights, contact support through :help_url. We may need to verify your identity before responding.',
                    ],
                ],
                [
                    'id' => 'cookies',
                    'title' => '8. Cookies',
                    'paragraphs' => [
                        'We use cookies and similar technologies to keep you logged in, remember preferences, and protect against abuse.',
                        'You can control cookies in your browser settings. Blocking essential cookies may prevent parts of the site from working.',
                    ],
                ],
                [
                    'id' => 'security',
                    'title' => '9. Security',
                    'paragraphs' => [
                        'We use industry-standard safeguards including encrypted connections, access controls, and monitoring for suspicious activity.',
                        'No online service is perfectly secure. Please use a strong password and report suspected unauthorised access immediately.',
                    ],
                ],
                [
                    'id' => 'children',
                    'title' => '10. Children',
                    'paragraphs' => [
                        'HustleSafe is not intended for users under 18. We do not knowingly collect data from children.',
                    ],
                ],
                [
                    'id' => 'changes-privacy',
                    'title' => '11. Updates',
                    'paragraphs' => [
                        'We may update this policy as our product or the law changes. The “Last updated” date at the top will change when we do.',
                        'Material changes may be highlighted in the app or by email where appropriate.',
                    ],
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function escrow(): array
    {
        return [
            'title' => 'Escrow Policy',
            'tagline' => 'How we hold and release your money',
            'summary' => $this->fill([
                'Escrow means the client’s payment is held safely by HustleSafe until the job is properly finished.',
                'The client funds escrow after awarding a freelancer. Work should not begin until escrow is funded.',
                'Funds release when the client marks the job complete, or automatically :auto_release_hours hours after the agreed delivery date if no dispute is open.',
                'We email the client on the delivery date and again at 24 and 36 hours after to prompt review.',
            ]),
            'sections' => $this->fillSections([
                [
                    'id' => 'what-is-escrow',
                    'title' => '1. What escrow means on HustleSafe',
                    'paragraphs' => [
                        'When a client awards a proposal, both parties receive a contract. The client then pays the agreed amount (including fees shown in the breakdown) into escrow through Paystack.',
                        'While funds are in escrow, neither party can withdraw them unilaterally. This protects the freelancer from non-payment and protects the client from paying without delivery.',
                    ],
                ],
                [
                    'id' => 'when-funded',
                    'title' => '2. When escrow must be funded',
                    'paragraphs' => [
                        'The client should fund escrow promptly after the freelancer confirms the award. Freelancers are not expected to start substantive work until escrow shows as funded.',
                        'If escrow is not funded within the time shown in the contract, the engagement may be cancelled under our standard rules.',
                    ],
                ],
                [
                    'id' => 'during-work',
                    'title' => '3. While work is in progress',
                    'bullets' => [
                        'Keep communication on the quest thread so there is a clear record.',
                        'Delivery date extensions may be requested by the freelancer (up to two per contract) with client consent.',
                        'Revisions included in the contract are for fixes within the original scope — not new features.',
                        'Scope changes that affect price or timeline should go through contract amendments.',
                    ],
                ],
                [
                    'id' => 'manual-release',
                    'title' => '4. Releasing funds early (client marks complete)',
                    'paragraphs' => [
                        'When the client is satisfied with delivery, they mark the job complete on the quest page. That confirms delivery and starts the release process.',
                        'For safety, there may be a short waiting period of up to :escrow_cooldown_hours hours after escrow was first funded before funds can move to the freelancer’s wallet (this helps catch certain fraud patterns).',
                        'Very high-value contracts may require additional platform authorisation before release.',
                    ],
                ],
                [
                    'id' => 'auto-release',
                    'title' => '5. Automatic release after the delivery date',
                    'paragraphs' => [
                        'If the client does not mark the job complete or open a dispute, escrow may automatically release to the freelancer :auto_release_hours hours after the agreed delivery date recorded in the contract.',
                        'We email the client on the agreed delivery date with a review reminder, then again 24 hours later, and a final reminder 36 hours after the delivery date.',
                        'These reminders give the client a clear window to approve work or open a dispute before automatic release.',
                    ],
                ],
                [
                    'id' => 'disputes-freeze',
                    'title' => '6. Disputes freeze escrow',
                    'paragraphs' => [
                        'If either party opens a dispute, automatic release stops and escrow stays protected while the case is handled under our Dispute Policy at :dispute_url.',
                        'Funds are only moved according to the dispute outcome or a mutual settlement recorded on the platform.',
                    ],
                ],
                [
                    'id' => 'fees',
                    'title' => '7. Fees and payouts',
                    'paragraphs' => [
                        'The contract shows the total escrow amount, platform service fee (currently around :platform_fee%), and the freelancer’s net payout.',
                        'When escrow releases, the freelancer’s wallet is credited with the net amount. Withdrawals to a Nigerian bank account follow our wallet rules and Paystack payout processing times.',
                    ],
                ],
                [
                    'id' => 'refunds',
                    'title' => '8. Refunds and cancellations',
                    'paragraphs' => [
                        'If a quest is cancelled before work starts and no dispute applies, escrow may return to the client under the rules shown at cancellation.',
                        'Partial refunds during a dispute follow the settlement or ruling described in the Dispute Policy.',
                    ],
                ],
                [
                    'id' => 'related',
                    'title' => '9. Related documents',
                    'paragraphs' => [
                        'This policy forms part of our Terms of Service at :terms_url and applies to every funded contract.',
                        'For disagreements, see the Dispute Policy at :dispute_url.',
                    ],
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dispute(): array
    {
        return [
            'title' => 'Dispute Policy',
            'tagline' => 'How we handle disagreements fairly',
            'summary' => $this->fill([
                'A dispute is a formal way to resolve a serious disagreement about a funded quest or completed job.',
                'Either the client or freelancer may open a dispute in allowed situations. Escrow is frozen while the case is active.',
                'Cases start with a :self_resolution_hours-hour self-resolution window where both parties can talk and propose a settlement.',
                'If timers are missed, the case escalates for staff review with clear evidence requirements.',
            ]),
            'sections' => $this->fillSections([
                [
                    'id' => 'philosophy',
                    'title' => '1. Our approach',
                    'bullets' => [
                        'Evidence first — decisions rely on dated uploads, messages, and contract terms on HustleSafe, not private side deals.',
                        'Time-boxed stages — each step has a visible countdown so good-faith users are not left waiting forever.',
                        'Transparent — both parties see the same thread, offers, and status updates.',
                        'Escalatable — missed deadlines move the case forward automatically.',
                        'Auditable — important actions are logged for staff review.',
                    ],
                ],
                [
                    'id' => 'when-open',
                    'title' => '2. When you can open a dispute',
                    'paragraphs' => [
                        'Disputes are available to the client and assigned freelancer once a proposal is accepted and escrow is funded (or shortly after completion in limited cases).',
                        'The contract value must meet the minimum dispute amount (currently :min_dispute_amount). Smaller matters should be resolved through support.',
                        'You may open a dispute within :dispute_completion_days days after a job is marked complete if new issues come to light.',
                        'Only one open dispute is allowed per quest at a time.',
                    ],
                    'bullets' => [
                        'Examples for clients: work not delivered, quality far below the agreed brief, refund issues after work started.',
                        'Examples for freelancers: client refuses to approve completed work, unfair milestone rejection, scope expanded without fair adjustment.',
                        'Both parties: long silence from the other side, material contract breach.',
                    ],
                ],
                [
                    'id' => 'how-open',
                    'title' => '3. How to open a dispute',
                    'paragraphs' => [
                        'From the quest page, choose the reason that best matches your situation and explain what happened in plain language.',
                        'Upload supporting files (screenshots, deliverables, briefs) through the dispute form. Strong evidence speeds up resolution.',
                        'When you submit, escrow release pauses and the other party is notified immediately.',
                    ],
                ],
                [
                    'id' => 'self-resolution',
                    'title' => '4. Stage 1 — Self-resolution (:self_resolution_hours hours)',
                    'paragraphs' => [
                        'The other party has :self_resolution_hours hours to respond with their side and evidence.',
                        'Both parties can post messages and settlement offers (for example a partial refund split) inside the dispute thread.',
                        'If you reach agreement, record the settlement on the platform so escrow can be split accordingly.',
                        'Adding a new message may refresh the response timer so both sides have fair time to reply.',
                    ],
                ],
                [
                    'id' => 'escalation',
                    'title' => '5. Stage 2 — Escalation and staff review',
                    'paragraphs' => [
                        'If required responses are missed, the dispute escalates to formal review. Both parties may be asked to submit a final evidence summary within :formal_ruling_hours hours.',
                        'HustleSafe staff (operations or admin team) review the contract, quest messages, deliverables, and dispute uploads.',
                        'Staff may request more information, suggest a settlement, or apply a ruling that splits escrow according to the evidence.',
                        'A platform dispute resolution fee of approximately :dispute_resolution_fee% may apply to certain formal outcomes as disclosed in the dispute flow.',
                    ],
                ],
                [
                    'id' => 'evidence',
                    'title' => '6. Evidence we consider',
                    'bullets' => [
                        'The generated contract and any accepted amendments or delivery extensions.',
                        'Quest thread messages and clarification history on HustleSafe.',
                        'Files uploaded as deliverables or attached to the dispute.',
                        'Timestamps showing when work was submitted or reviewed.',
                        'Client-attributed delay flags where scope changes were tagged in messages.',
                    ],
                    'paragraphs' => [
                        'We generally cannot consider “he said / she said” without supporting proof. Keep important agreements on-platform.',
                    ],
                ],
                [
                    'id' => 'outcomes',
                    'title' => '7. Possible outcomes',
                    'bullets' => [
                        'Full release to the freelancer.',
                        'Full refund to the client.',
                        'Partial split agreed by both parties.',
                        'Partial split decided by staff after review.',
                        'Dispute withdrawn if parties solve the issue privately on-platform.',
                    ],
                ],
                [
                    'id' => 'appeals',
                    'title' => '8. Appeals and repeat disputes',
                    'paragraphs' => [
                        'Limited appeals may be available where our rules allow. Abuse of the dispute system can affect account standing.',
                        'Users with a pattern of lost disputes may receive additional review under our trust policies.',
                    ],
                ],
                [
                    'id' => 'related-dispute',
                    'title' => '9. Related documents',
                    'paragraphs' => [
                        'Escrow rules during disputes are in our Escrow Policy at :escrow_url.',
                        'General platform rules are in the Terms of Service at :terms_url.',
                    ],
                ],
            ]),
        ];
    }
}
