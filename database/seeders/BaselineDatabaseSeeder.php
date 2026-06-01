<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent baseline reference data — safe to run after migrate on an empty database.
 * Includes demo marketplace users (20 freelancers, 10 clients) for local testing.
 */
class BaselineDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(NigeriaGeoSeeder::class);
        $this->call(QuestCategorySeeder::class);

        if (Schema::hasTable('support_ticket_issue_groups')) {
            $this->call(SupportTicketIssueGroupSeeder::class);
        }

        if (Schema::hasTable('staff_response_templates')) {
            $this->call(StaffResponseTemplateSeeder::class);
        }

        if (Schema::hasTable('users')) {
            $this->call(FakeMarketplaceUsersSeeder::class);
        }
    }
}
