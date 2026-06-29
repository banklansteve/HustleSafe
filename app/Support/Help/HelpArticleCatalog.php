<?php

namespace App\Support\Help;

use App\Support\EscrowAutoReleasePolicy;
use App\Support\PlatformFeeDisclosure;
use App\Support\PlatformSettings;

final class HelpArticleCatalog
{
    /**
     * @return list<string>
     */
    public static function featuredSlugs(): array
    {
        return [
            'account-setup',
            'post-a-quest',
            'submit-a-proposal',
            'fund-escrow',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listing(string $audience = 'all', string $query = ''): array
    {
        $needle = mb_strtolower(trim($query));

        return collect(self::definitions())
            ->map(fn (array $def) => self::buildFromDefinition($def))
            ->filter(function (array $article) use ($audience, $needle) {
                if (! in_array($article['audience'], ['all', $audience], true) && $audience !== 'all') {
                    // Show all articles on hub — audience badge helps users pick relevant ones.
                }

                if ($needle === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    $article['title'],
                    $article['summary'],
                    $article['audience_label'],
                    implode(' ', $article['search_text']),
                ]));

                return str_contains($haystack, $needle);
            })
            ->sortBy('sort_order')
            ->values()
            ->map(fn (array $article) => [
                'slug' => $article['slug'],
                'title' => $article['title'],
                'summary' => $article['summary'],
                'audience' => $article['audience'],
                'audience_label' => $article['audience_label'],
                'read_minutes' => $article['read_minutes'],
                'sort_order' => $article['sort_order'],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function build(string $slug): ?array
    {
        foreach (self::definitions() as $def) {
            if ($def['slug'] === $slug) {
                return self::buildFromDefinition($def);
            }
        }

        return null;
    }

    /**
     * @return array<string, string|int|float>
     */
    private static function vars(): array
    {
        $fee = PlatformFeeDisclosure::formatPercent();
        $autoHours = (string) EscrowAutoReleasePolicy::releaseHours();
        $disclosure = PlatformFeeDisclosure::toArray();

        return [
            'platform_fee' => $fee,
            'auto_release_hours' => $autoHours,
            'paystack_funding' => $disclosure['paystack_funding_fee'],
            'paystack_payout' => $disclosure['paystack_payout_fee'],
            'vat_percent' => $disclosure['vat_percent_label'],
            'escrow_cooldown_hours' => (string) PlatformSettings::escrowReleaseCooldownHours(),
            'terms_url' => route('legal.terms'),
            'privacy_url' => route('legal.privacy'),
            'escrow_policy_url' => route('legal.escrow'),
            'dispute_policy_url' => route('legal.dispute'),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function fill(array $lines): array
    {
        $replace = [];
        foreach (self::vars() as $key => $value) {
            $replace[':'.$key] = (string) $value;
        }

        return array_map(fn (string $line) => strtr($line, $replace), $lines);
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    private static function buildFromDefinition(array $def): array
    {
        $related = collect($def['related'] ?? [])
            ->map(function (string $slug) {
                foreach (self::definitions() as $row) {
                    if ($row['slug'] === $slug) {
                        return [
                            'slug' => $slug,
                            'title' => $row['title'],
                            'href' => route('help.show', $slug),
                        ];
                    }
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();

        $steps = array_map(function (array $step) {
            return [
                'title' => strtr($step['title'] ?? '', self::replaceMap()),
                'body' => strtr($step['body'] ?? '', self::replaceMap()),
            ];
        }, $def['steps'] ?? []);

        $whatNext = self::fill($def['what_happens_next'] ?? []);

        $faqs = array_map(function (array $faq) {
            return [
                'question' => strtr($faq['question'], self::replaceMap()),
                'answer' => strtr($faq['answer'], self::replaceMap()),
            ];
        }, $def['faqs'] ?? []);

        $searchText = array_merge(
            [$def['title'], $def['summary']],
            array_column($steps, 'title'),
            array_column($steps, 'body'),
            $whatNext,
            array_column($faqs, 'question'),
            array_column($faqs, 'answer'),
            $def['search_keywords'] ?? [],
        );

        return [
            'slug' => $def['slug'],
            'title' => $def['title'],
            'summary' => strtr($def['summary'], self::replaceMap()),
            'audience' => $def['audience'],
            'audience_label' => $def['audience_label'],
            'sort_order' => $def['sort_order'],
            'read_minutes' => $def['read_minutes'] ?? 4,
            'steps' => $steps,
            'what_happens_next' => $whatNext,
            'faqs' => $faqs,
            'related_articles' => $related,
            'search_text' => $searchText,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function replaceMap(): array
    {
        $map = [];
        foreach (self::vars() as $key => $value) {
            $map[':'.$key] = (string) $value;
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function definitions(): array
    {
        return [
            self::accountSetup(),
            self::postAQuest(),
            self::submitProposal(),
            self::fundEscrow(),
            self::markJobComplete(),
            self::raiseDispute(),
            self::withdrawFunds(),
            self::kycVerification(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function accountSetup(): array
    {
        return [
            'slug' => 'account-setup',
            'title' => 'Setting up your HustleSafe account',
            'summary' => 'Create your account, choose how you will use the platform, and get your profile ready before your first quest or proposal.',
            'audience' => 'all',
            'audience_label' => 'Everyone',
            'sort_order' => 1,
            'read_minutes' => 5,
            'search_keywords' => ['register', 'sign up', 'sponsor', 'hustler', 'profile', 'password'],
            'steps' => [
                ['title' => 'Pick your role', 'body' => 'Choose Project Sponsor if you hire talent and fund escrow. Choose Safe Hustler if you deliver work and receive payouts. You can update your profile later, but this sets your default experience.'],
                ['title' => 'Complete registration', 'body' => 'Enter your name, email, phone, and Nigerian address (state and LGA). Use a phone number you can receive SMS on — we may use it for security alerts.'],
                ['title' => 'Agree to the Terms and Privacy Policy', 'body' => 'Read and accept our Terms of Service and Privacy Policy. These explain how escrow, disputes, and your data work on HustleSafe.'],
                ['title' => 'Build your profile', 'body' => 'Add a clear photo, headline, and skills (for hustlers). Sponsors should add company details if posting on behalf of a business. A complete profile builds trust before money changes hands.'],
                ['title' => 'Verify your email', 'body' => 'Click the link in your welcome email. Some features stay limited until your email is confirmed.'],
            ],
            'what_happens_next' => [
                'Sponsors can post a quest and review proposals once your account is active.',
                'Hustlers can browse open quests and submit proposals after completing key profile fields.',
                'Higher-value work may require identity verification (KYC) — see our KYC guide.',
            ],
            'faqs' => [
                ['question' => 'Can I switch between sponsor and hustler later?', 'answer' => 'Your account type is set at registration. Contact support if your business model truly changed — we will advise the safest path without mixing escrow roles on one profile.'],
                ['question' => 'Do I need a CAC registration to sign up?', 'answer' => 'No for basic signup. Business verification may be required later for high-value quests or certain categories.'],
                ['question' => 'Is HustleSafe only for Lagos?', 'answer' => 'No — we are built for Nigeria. You can work remotely with partners in any state as long as payments stay on-platform through escrow.'],
                ['question' => 'Why do you need my address?', 'answer' => 'It helps with verification, fraud prevention, and matching you to relevant local quests where location matters.'],
            ],
            'related' => ['kyc-verification', 'post-a-quest', 'submit-a-proposal'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function postAQuest(): array
    {
        return [
            'slug' => 'post-a-quest',
            'title' => 'How to post a quest (hire talent)',
            'summary' => 'Describe the job, set a realistic budget and deadline, and publish so verified hustlers can send proposals.',
            'audience' => 'clients',
            'audience_label' => 'Project Sponsors',
            'sort_order' => 2,
            'read_minutes' => 6,
            'search_keywords' => ['create quest', 'hire', 'job', 'brief', 'budget', 'deadline'],
            'steps' => [
                ['title' => 'Open Create quest', 'body' => 'From your dashboard, tap Create quest. The guided form saves progress in your browser until you submit — nothing goes live until the final step.'],
                ['title' => 'Describe the work clearly', 'body' => 'Write what you need, deliverables, and quality expectations in plain language. Avoid phone numbers or WhatsApp in the description — keep communication on HustleSafe so escrow protects you.'],
                ['title' => 'Set category, budget, and deadline', 'body' => 'Pick the closest category so the right hustlers see your quest. Enter a realistic budget in naira and a proposal deadline (when hustlers must submit quotes).'],
                ['title' => 'Review fees before publishing', 'body' => 'You will see how platform fees and statutory charges work. Our platform fee is currently :platform_fee% of the job amount, plus Paystack charges when you fund escrow later. Full details are in our Escrow Policy.'],
                ['title' => 'Publish and wait for proposals', 'body' => 'Submit the quest. It becomes visible to hustlers who match your category. You can shortlist favourites, message for clarifications, and award one proposal when ready.'],
            ],
            'what_happens_next' => [
                'Hustlers submit proposals with price, timeline, and scope.',
                'You compare proposals, ask questions in the quest thread, and award one hustler.',
                'Both parties confirm award terms, then you fund escrow before work should begin.',
                'Until escrow is funded, the hustler is not obligated to start billable work.',
            ],
            'faqs' => [
                ['question' => 'How much should I budget?', 'answer' => 'Check similar quests in your category. Too low attracts spam; too high without detail attracts mismatched quotes. Be honest about scope — you can negotiate in proposals.'],
                ['question' => 'Can I edit a quest after posting?', 'answer' => 'You can update many details while the quest is open and before award. Major changes after award may require a contract amendment with the hustler\'s consent.'],
                ['question' => 'Why can\'t I paste my phone number?', 'answer' => 'Off-platform contact bypasses escrow protection and is against our rules. Use the quest thread for all project discussion.'],
                ['question' => 'What if I get no proposals?', 'answer' => 'Improve your description, adjust budget or deadline, or widen the category. Our team can suggest listing tips via support.'],
                ['question' => 'When do I pay?', 'answer' => 'Not when you post — payment happens when you fund escrow after awarding a proposal you are happy with.'],
            ],
            'related' => ['fund-escrow', 'submit-a-proposal', 'account-setup'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function submitProposal(): array
    {
        return [
            'slug' => 'submit-a-proposal',
            'title' => 'How to submit a proposal (win work)',
            'summary' => 'Quote fairly, explain your approach, and send a proposal that helps the sponsor trust you — without moving payment off-platform.',
            'audience' => 'freelancers',
            'audience_label' => 'Safe Hustlers',
            'sort_order' => 3,
            'read_minutes' => 6,
            'search_keywords' => ['apply', 'bid', 'quote', 'offer', 'pitch', 'freelance'],
            'steps' => [
                ['title' => 'Find a quest that fits', 'body' => 'Browse open quests in your skills. Read the full brief, deadline, and budget range before you invest time in a proposal.'],
                ['title' => 'Open Submit proposal', 'body' => 'From the quest page, start your proposal. You can save progress — complete all sections before sending.'],
                ['title' => 'Write a clear pitch', 'body' => 'Explain how you will deliver, timeline, and what is included. Reference similar work if you have it. Do not paste phone numbers, emails, or bank details — that can get your account restricted.'],
                ['title' => 'Build your pricing breakdown', 'body' => 'Enter professional fee, materials, travel if needed. The platform fee (:platform_fee% of subtotal) is calculated automatically and shown to the sponsor in the total.'],
                ['title' => 'Set your delivery deadline and agree to terms', 'body' => 'Propose a finish date you can honestly meet. Accept the Terms of Service and Privacy Policy — your proposal snapshot is stored for audit.'],
                ['title' => 'Submit and monitor the quest', 'body' => 'Send the proposal. The sponsor may shortlist, ask clarifications, or award. Respond quickly in the quest thread — silence hurts your chances.'],
            ],
            'what_happens_next' => [
                'The sponsor reviews all proposals and may message you for details.',
                'If awarded, both of you confirm scope, price, and deadline.',
                'Wait until the sponsor funds escrow before starting billable work.',
                'After funding, a contract is generated and the delivery clock starts.',
            ],
            'faqs' => [
                ['question' => 'Should I start work before escrow is funded?', 'answer' => 'No. Wait until escrow is funded and confirmed on-platform. Work started before funding is not protected by our escrow guarantee.'],
                ['question' => 'Can I edit my proposal after submitting?', 'answer' => 'You may have a short edit window after submit — check the proposal page timer. After that, message the sponsor or withdraw and resubmit if the quest is still open.'],
                ['question' => 'What if the sponsor asks me to charge less off-platform?', 'answer' => 'Decline. Off-platform payment removes escrow protection and violates our rules. Report the behaviour if pressured.'],
                ['question' => 'How is the platform fee taken?', 'answer' => 'It is included in the quote the sponsor sees. When escrow releases, you receive your net payout after the platform fee — shown in your contract breakdown.'],
                ['question' => 'What if my proposal is declined?', 'answer' => 'You will be notified. The quest stays open for other proposals unless the sponsor closes it. Improve your next pitch based on feedback if they gave any.'],
            ],
            'related' => ['fund-escrow', 'mark-job-complete', 'account-setup'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fundEscrow(): array
    {
        return [
            'slug' => 'fund-escrow',
            'title' => 'How to fund escrow (pay safely)',
            'summary' => 'Lock the agreed amount in HustleSafe escrow via Paystack so the hustler knows you are serious and your money stays protected until delivery.',
            'audience' => 'clients',
            'audience_label' => 'Project Sponsors',
            'sort_order' => 4,
            'read_minutes' => 5,
            'search_keywords' => ['pay', 'payment', 'paystack', 'checkout', 'deposit', 'lock funds'],
            'steps' => [
                ['title' => 'Award a proposal first', 'body' => 'Select the hustler you want to work with and confirm award terms together — price, deliverables, and delivery date. Escrow funding unlocks only after both sides confirm.'],
                ['title' => 'Review the contract snapshot', 'body' => 'Check the total amount including platform fee and any statutory lines in the breakdown. This is what you will fund — not a surprise at checkout.'],
                ['title' => 'Click Fund escrow / Pay with Paystack', 'body' => 'From the proposal or quest page, start checkout. You will be redirected to Paystack to pay by card, bank transfer, or other methods Paystack supports.'],
                ['title' => 'Complete payment and confirm', 'body' => 'After Paystack succeeds, return to HustleSafe and confirm escrow is funded. The hustler receives a notification that work may begin.'],
                ['title' => 'Keep communication on-platform', 'body' => 'Use the quest thread for updates and files. Escrow protection applies to work tracked on HustleSafe.'],
            ],
            'what_happens_next' => [
                'Funds sit in escrow — they are not in the hustler\'s pocket yet.',
                'The hustler delivers against the contract deadline.',
                'You review work and either mark the job complete to release funds, or open a dispute if something is wrong.',
                'If you take no action after the agreed delivery date, we email you reminders and funds may auto-release after :auto_release_hours hours unless a dispute is open.',
            ],
            'faqs' => [
                ['question' => 'What fees do I pay on top of the quote?', 'answer' => 'Paystack charges :paystack_funding on escrow funding. VAT (:vat_percent) applies to the platform fee portion. Your proposal breakdown shows the platform fee (:platform_fee%).'],
                ['question' => 'Can I get a refund if work never starts?', 'answer' => 'If the hustler does not deliver and you open a valid dispute, escrow can be returned according to our Dispute Policy. Do not pay off-platform expecting a refund we cannot track.'],
                ['question' => 'How long do I have to fund escrow?', 'answer' => 'After award, there is a funding window shown on your contract. Fund promptly so the hustler can schedule your job.'],
                ['question' => 'Is my card details stored by HustleSafe?', 'answer' => 'Payments are processed by Paystack. We do not store your full card number on our servers.'],
                ['question' => 'What if Paystack fails but money left my bank?', 'answer' => 'Contact support with your Paystack reference. Bank transfers can take a few minutes to confirm — do not pay twice until we verify.'],
            ],
            'related' => ['mark-job-complete', 'raise-a-dispute', 'post-a-quest'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function markJobComplete(): array
    {
        return [
            'slug' => 'mark-job-complete',
            'title' => 'How to mark a job complete (release payment)',
            'summary' => 'When you are satisfied with delivery, confirm completion so escrow can release to the hustler — or open a dispute first if something is wrong.',
            'audience' => 'clients',
            'audience_label' => 'Project Sponsors',
            'sort_order' => 5,
            'read_minutes' => 5,
            'search_keywords' => ['approve', 'release', 'complete', 'delivery', 'accept work', 'pay freelancer'],
            'steps' => [
                ['title' => 'Review all deliverables', 'body' => 'Open files and outputs in the quest thread or deliverables section. Compare against the contract and revision policy (included revisions are listed on your contract).'],
                ['title' => 'Request revisions if needed', 'body' => 'If work is close but not there yet, ask for a revision inside the platform before approving. Respect the number of revisions agreed in the contract.'],
                ['title' => 'Acknowledge delivery when files arrive', 'body' => 'Some flows ask you to confirm you received deliverables. This starts the release clock — it does not automatically pay the hustler until you release funds or the auto-release window passes.'],
                ['title' => 'Mark complete / Release funds', 'body' => 'When genuinely satisfied, use Mark complete or Release funds on the quest page. There may be a short safety cooldown (:escrow_cooldown_hours hours) after escrow was first funded before release is allowed.'],
                ['title' => 'Leave an honest review', 'body' => 'After completion, rate your experience. Reviews help other sponsors and reward good hustlers.'],
            ],
            'what_happens_next' => [
                'Escrow releases to the hustler\'s wallet minus the platform fee shown in the contract.',
                'The hustler can withdraw to their Nigerian bank account when eligible.',
                'The quest moves to completed status and the contract is closed unless a dispute is opened within allowed windows.',
            ],
            'faqs' => [
                ['question' => 'What if I forget to mark complete?', 'answer' => 'We email you on the delivery date, again 24 hours later, and a final reminder 36 hours later. If you do nothing and do not open a dispute, escrow may auto-release after :auto_release_hours hours from the agreed delivery date.'],
                ['question' => 'Can I release payment before reviewing?', 'answer' => 'Only if you genuinely accept the work. Releasing tells the system you are satisfied — use disputes instead if there is a problem.'],
                ['question' => 'The work is late — should I still approve?', 'answer' => 'If late delivery matters, message the hustler first. You may open a dispute for material breach instead of releasing. Client-attributed delays from scope changes may adjust expectations — check the contract timeline.'],
                ['question' => 'Why is release blocked?', 'answer' => 'Common reasons: escrow cooldown not finished, delivery not acknowledged, active dispute, or high-value contract needing platform authorisation. The page shows the reason.'],
            ],
            'related' => ['raise-a-dispute', 'fund-escrow', 'withdraw-funds'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function raiseDispute(): array
    {
        return [
            'slug' => 'raise-a-dispute',
            'title' => 'How to raise a dispute',
            'summary' => 'Structured dispute resolution: peer negotiation, staff mediation, Super Admin approval, and one binding appeal — escrow stays protected throughout.',
            'audience' => 'all',
            'audience_label' => 'Sponsors & Hustlers',
            'sort_order' => 6,
            'read_minutes' => 6,
            'search_keywords' => ['complaint', 'refund', 'problem', 'mediation', 'conflict', 'not delivered'],
            'steps' => [
                ['title' => 'Try to resolve in the quest thread first', 'body' => 'Many issues are misunderstandings. Message clearly about what is missing and give a fair chance to fix before escalating.'],
                ['title' => 'Open Disputes from the quest page', 'body' => 'Choose Raise dispute when escrow is funded (or within allowed time after completion for certain issues). Pick the reason that best matches your situation.'],
                ['title' => 'Explain what happened plainly', 'body' => 'Write dates, what was agreed, and what went wrong. Avoid insults — staff review your words as evidence.'],
                ['title' => 'Upload proof', 'body' => 'Attach screenshots, files, briefs, and deliverables. Strong evidence speeds up resolution. Chat history on HustleSafe counts — off-platform promises do not.'],
                ['title' => 'Negotiate with structured proposals', 'body' => 'Each party gets up to two formal proposals (payment splits, revisions, extensions, scope changes). Accept, counter, or reject final offers within 24-hour deadlines shown on the dispute page.'],
                ['title' => 'Acknowledge binding mediation if needed', 'body' => 'If negotiation fails, staff mediate. Before the final decision you must confirm that platform outcomes are binding. Posting a quest or sending a proposal means you already agreed to this — external mediation is not available.'],
                ['title' => 'Use your one appeal carefully', 'body' => 'After a Super Admin decision you may have 48 hours to appeal with reasons and a fair counter-proposal. The post-appeal outcome is final.'],
            ],
            'what_happens_next' => [
                'Escrow release pauses while the dispute is active.',
                'Phase 1: Each party may submit up to two negotiation proposals with accept/counter/reject options.',
                'If you agree, Customer Support approves the settlement before funds move (4-day appeal window after approval).',
                'If you disagree after negotiation, staff review all proposals and evidence, then Super Admin approves a decision.',
                'You receive email and in-app notices at every stage with clear deadlines and next steps.',
                'You may appeal once during the enforcement window; the final outcome is binding on HustleSafe.',
            ],
            'faqs' => [
                ['question' => 'When is it too late to dispute?', 'answer' => 'Open before auto-release if delivery is the issue. After completion, you generally have 14 days for newly discovered problems. Minimum contract value rules apply — small amounts may be directed to support instead.'],
                ['question' => 'Will I automatically get a refund?', 'answer' => 'Not always. Staff or agreed settlements decide based on evidence and contract terms. False or abusive disputes can affect account standing.'],
                ['question' => 'Can hustlers dispute too?', 'answer' => 'Yes — for example if a sponsor refuses to approve completed work without reason. The same evidence rules apply.'],
                ['question' => 'Does disputing cost money?', 'answer' => 'Opening a dispute is free. Certain formal outcomes may include a small resolution fee disclosed in the flow — see our Dispute Policy at :dispute_policy_url.'],
                ['question' => 'What if the other party goes silent?', 'answer' => 'Missed negotiation deadlines escalate the case to staff mediation automatically. Keep your evidence uploaded and check email for updates.'],
                ['question' => 'Can I take this to court or external mediation?', 'answer' => 'No for outcomes resolved through HustleSafe’s dispute process. By posting quests and proposals you agreed to platform binding resolution. See our Dispute Policy at :dispute_policy_url.'],
                ['question' => 'How many times can I appeal?', 'answer' => 'One appeal per dispute during an open appeal window. The decision after appeal review is final.'],
            ],
            'related' => ['mark-job-complete', 'fund-escrow', 'kyc-verification'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function withdrawFunds(): array
    {
        return [
            'slug' => 'withdraw-funds',
            'title' => 'How to withdraw your earnings',
            'summary' => 'Move money from your HustleSafe wallet to your Nigerian bank account after escrow has released to you.',
            'audience' => 'freelancers',
            'audience_label' => 'Safe Hustlers',
            'sort_order' => 7,
            'read_minutes' => 4,
            'search_keywords' => ['payout', 'bank', 'wallet', 'transfer', 'get paid', 'withdrawal'],
            'steps' => [
                ['title' => 'Complete KYC if prompted', 'body' => 'Withdrawals require identity verification at certain tiers. Finish BVN/NIN or document steps in Verifications before your first payout if we ask.'],
                ['title' => 'Add or confirm your bank details', 'body' => 'In Wallet or Account settings, enter a Nigerian bank account in your legal name. Typos cause failed payouts — double-check account number and bank.'],
                ['title' => 'Wait for escrow release', 'body' => 'Only released escrow appears in your available balance. Pending jobs still in progress are not withdrawable yet.'],
                ['title' => 'Request withdrawal', 'body' => 'Open Wallet, enter amount (respect minimum payout if shown), and confirm. Paystack processes the transfer — payout fee is typically :paystack_payout.'],
                ['title' => 'Track status', 'body' => 'Payouts may show pending, processing, or paid. Bank delays happen — wait the stated window before raising a ticket.'],
            ],
            'what_happens_next' => [
                'Funds arrive in your bank account if details are correct.',
                'Failed payouts return to your wallet — fix bank details and retry or contact support.',
                'Your transaction history shows the payout reference for your records.',
            ],
            'faqs' => [
                ['question' => 'Why is my balance zero after completing work?', 'answer' => 'The sponsor must release escrow or auto-release must run. Until then, money stays in escrow, not your wallet.'],
                ['question' => 'How long do withdrawals take?', 'answer' => 'Many arrive within minutes to one business day depending on bank and Paystack. Weekends and public holidays can add delay.'],
                ['question' => 'Can I withdraw to someone else\'s account?', 'answer' => 'No — account name must match your verified identity to prevent fraud.'],
                ['question' => 'Is there a minimum withdrawal?', 'answer' => 'Yes — check Wallet for the current minimum. Small balances accumulate until you reach it.'],
                ['question' => 'What if payout failed?', 'answer' => 'Open support with the payout reference. Common fixes: wrong account number, bank maintenance, or incomplete KYC.'],
            ],
            'related' => ['kyc-verification', 'mark-job-complete', 'submit-a-proposal'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function kycVerification(): array
    {
        return [
            'slug' => 'kyc-verification',
            'title' => 'Identity verification (KYC)',
            'summary' => 'Verify who you are to unlock higher limits, build trust, and enable withdrawals — using BVN, NIN, or documents as requested.',
            'audience' => 'all',
            'audience_label' => 'Everyone',
            'sort_order' => 8,
            'read_minutes' => 5,
            'search_keywords' => ['verify', 'BVN', 'NIN', 'identity', 'tier', 'limits', 'documents'],
            'steps' => [
                ['title' => 'Open Verifications in your account', 'body' => 'Go to Account or the verification prompt when a feature is locked. You will see which step is required next.'],
                ['title' => 'Choose the requested verification type', 'body' => 'Common steps include BVN, NIN, utility bill, or government ID photo. Only submit your own details — impersonation leads to permanent ban.'],
                ['title' => 'Upload clear photos', 'body' => 'Use good lighting, no blur, full document edges visible. Mask sensitive numbers in screenshots if the form asks only for partial data.'],
                ['title' => 'Submit and wait for review', 'body' => 'Automated checks run first; some cases need manual review within a few business days. You will get email or in-app status updates.'],
                ['title' => 'Upgrade tier when eligible', 'body' => 'Higher tiers unlock larger quest values, more proposals, or faster payouts. The app shows what each tier unlocks when you hit a limit.'],
            ],
            'what_happens_next' => [
                'Verified users show trust badges on their profile.',
                'Limits increase — you can post higher budgets or accept larger jobs.',
                'Withdrawals and certain sponsor actions unlock when requirements are met.',
            ],
            'faqs' => [
                ['question' => 'Is my BVN safe with HustleSafe?', 'answer' => 'We use regulated identity partners and store data under our Privacy Policy. We never ask you to share BVN in public quest messages.'],
                ['question' => 'Why was my verification rejected?', 'answer' => 'Common reasons: blurry photo, name mismatch, expired ID, or document already used on another account. Fix and resubmit or contact support.'],
                ['question' => 'Do sponsors need KYC too?', 'answer' => 'Basic posting may work at low tiers. Funding large escrow amounts or repeated high-value quests may require sponsor verification.'],
                ['question' => 'Can I use a friend\'s utility bill?', 'answer' => 'No — proof of address must match your profile. Fraudulent documents result in account closure.'],
            ],
            'related' => ['account-setup', 'withdraw-funds', 'fund-escrow'],
        ];
    }
}
