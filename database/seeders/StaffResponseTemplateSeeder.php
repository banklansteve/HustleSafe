<?php

namespace Database\Seeders;

use App\Models\StaffResponseTemplate;
use Illuminate\Database\Seeder;

class StaffResponseTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'freelancer-kyc-no-proposal',
                'situation_key' => 'freelancer_kyc_no_proposal_14d',
                'category' => 'retention',
                'title' => 'KYC complete — first proposal nudge',
                'subject' => 'Ready to send your first proposal?',
                'body' => "Hi :name,\n\nYour verification is complete — great work. The next step is sending a focused proposal on a Quest that matches your skills.\n\nI can suggest active Quests in your categories or review a draft pitch before you submit. Reply if you'd like a hand getting started.",
                'policy_tags' => ['retention', 'onboarding', 'freelancer'],
                'sort_order' => 10,
            ],
            [
                'slug' => 'client-no-quest-posted',
                'situation_key' => 'client_no_quest_21d',
                'category' => 'retention',
                'title' => 'Client — no Quest posted',
                'subject' => 'Need help posting your first Quest?',
                'body' => "Hi :name,\n\nYou joined HustleSafe a few weeks ago but haven't published a Quest yet. I can help you draft a clear brief, choose the right category, and set a fair budget so freelancers respond faster.\n\nReply if you'd like a quick walkthrough — it only takes a few minutes.",
                'policy_tags' => ['retention', 'onboarding', 'client'],
                'sort_order' => 20,
            ],
            [
                'slug' => 'quest-awarded-no-escrow',
                'situation_key' => 'awarded_no_escrow_funded',
                'category' => 'escrow',
                'title' => 'Awarded — escrow not funded',
                'subject' => 'Fund escrow for “:quest_title”',
                'body' => "Hi :name,\n\nYou awarded :freelancer_name on “:quest_title” (:quest_reference), but escrow has not been funded yet.\n\nFunding escrow lets work begin safely for both sides — all payments stay protected on HustleSafe until delivery is confirmed.\n\nIf you need help with checkout or have questions about escrow, reply here and we'll assist.",
                'policy_tags' => ['escrow', 'payments', 'client'],
                'sort_order' => 30,
            ],
            [
                'slug' => 'freelancer-rating-drop-coaching',
                'situation_key' => 'freelancer_rating_drop',
                'category' => 'quality',
                'title' => 'Freelancer — rating drop coaching',
                'subject' => 'We are here to help you bounce back',
                'body' => "Hi :name,\n\nWe noticed your recent reviews (:rating_after average over the last few weeks) are below your usual standard (:rating_before overall).\n\nThis can happen after a tough project — we're not here to penalise you, but to help. Reply if you'd like tips on scope clarity, delivery updates, or dispute prevention.\n\nConsistent communication often makes the biggest difference.",
                'policy_tags' => ['quality', 'coaching', 'freelancer'],
                'sort_order' => 40,
            ],
            [
                'slug' => 'dispute-no-evidence',
                'situation_key' => 'dispute_open_no_evidence',
                'category' => 'dispute',
                'title' => 'Dispute — evidence reminder',
                'subject' => 'Please submit evidence for your dispute',
                'body' => "Hi :name,\n\nYour dispute on “:quest_title” is open, but we have not received supporting evidence yet.\n\nUpload screenshots, delivery files, or message excerpts in the dispute centre so our team can review fairly. Disputes without evidence may be delayed or closed.\n\nReply if you are unsure what to include — we can guide you.",
                'policy_tags' => ['dispute', 'evidence', 'policy'],
                'sort_order' => 50,
            ],
            [
                'slug' => 'off-platform-payment-warning',
                'situation_key' => 'off_platform_payment_flagged',
                'category' => 'trust',
                'title' => 'Off-platform payment — policy reminder',
                'subject' => 'Important: keep payments on HustleSafe',
                'body' => "Hi :name,\n\nOur systems flagged language in a recent conversation that may relate to off-platform payment.\n\nAll payments must go through HustleSafe escrow — this protects you and the other party. Repeated attempts to move deals off-platform can result in account restrictions.\n\nIf this was a misunderstanding, reply and we'll clarify. If you need help funding escrow on “:quest_title”, we're happy to assist.",
                'policy_tags' => ['trust', 'payments', 'policy', 'warning'],
                'sort_order' => 60,
            ],
        ];

        foreach ($templates as $data) {
            StaffResponseTemplate::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...$data,
                    'is_active' => true,
                    'placeholders' => null,
                ],
            );
        }
    }
}
