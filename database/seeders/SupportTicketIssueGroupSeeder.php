<?php

namespace Database\Seeders;

use App\Models\SupportTicketIssueGroup;
use Illuminate\Database\Seeder;

class SupportTicketIssueGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['key' => 'account_verification', 'label' => 'Account & Verification', 'sort_order' => 10],
            ['key' => 'payments_escrow', 'label' => 'Payments & Escrow', 'sort_order' => 20],
            ['key' => 'disputes_contracts', 'label' => 'Disputes & Contracts', 'sort_order' => 30],
            ['key' => 'technical_issues', 'label' => 'Technical Issues', 'sort_order' => 40],
            ['key' => 'fraud_security', 'label' => 'Fraud & Security', 'sort_order' => 50],
            ['key' => 'quest_proposals', 'label' => 'Quest & Proposals', 'sort_order' => 60],
            ['key' => 'reviews_ratings', 'label' => 'Reviews & Ratings', 'sort_order' => 70],
            ['key' => 'general_enquiries', 'label' => 'General Enquiries', 'sort_order' => 80],
        ];

        foreach ($groups as $group) {
            SupportTicketIssueGroup::query()->updateOrCreate(
                ['key' => $group['key']],
                [
                    'label' => $group['label'],
                    'sort_order' => $group['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
