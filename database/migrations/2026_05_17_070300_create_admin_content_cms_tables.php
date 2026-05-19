<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('trigger_event')->index();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->json('blocks')->nullable();
            $table->json('theme')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->json('blocks')->nullable();
            $table->json('theme')->nullable();
            $table->json('variables')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamps();

            $table->index(['email_template_id', 'created_at']);
        });

        Schema::create('email_template_analytics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->date('metric_date')->index();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('unsubscribe_count')->default(0);
            $table->string('provider')->nullable();
            $table->timestamps();

            $table->unique(['email_template_id', 'metric_date']);
        });

        Schema::create('announcement_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('message', 500);
            $table->string('link_url')->nullable();
            $table->string('link_text')->nullable();
            $table->string('color', 24)->default('brand')->index();
            $table->string('segment', 40)->default('all')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('dismissible')->default(true);
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('help_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('display_order')->default(0)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('help_faq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('help_section_id')->constrained('help_sections')->cascadeOnDelete();
            $table->string('question');
            $table->longText('answer');
            $table->string('audience', 32)->default('all')->index();
            $table->json('search_keywords')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0)->index();
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('versionable_type');
            $table->unsignedBigInteger('versionable_id');
            $table->index(['versionable_type', 'versionable_id'], 'content_versions_versionable_idx');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->text('change_note')->nullable();
            $table->timestamps();
        });

        Schema::create('help_search_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('query');
            $table->unsignedInteger('results_count')->default(0);
            $table->string('audience', 32)->nullable();
            $table->timestamps();

            $table->index(['results_count', 'created_at']);
        });

        $templates = [
            ['welcome_verify_email', 'auth.registered', 'Welcome / verify email', 'Welcome to HustleSafe, {{user.first_name}}', 'Verify your email to unlock your account.', [['type' => 'text', 'content' => 'Welcome to HustleSafe, {{user.first_name}}.'], ['type' => 'button', 'label' => 'Verify email', 'url' => '{{verification.url}}']]],
            ['quest_posted_confirmation', 'quest.posted', 'Quest posted confirmation', 'Your Quest is live: {{quest.title}}', 'Freelancers can now discover your Quest.', [['type' => 'text', 'content' => 'Your Quest "{{quest.title}}" is now live.']]],
            ['proposal_received', 'proposal.received', 'Proposal received', 'New proposal for {{quest.title}}', 'A freelancer has submitted a proposal.', [['type' => 'text', 'content' => '{{freelancer.name}} submitted a proposal for {{quest.title}}.']]],
            ['dispute_opened', 'dispute.opened', 'Dispute opened alert', 'Dispute opened for {{quest.title}}', 'Review the dispute and respond before the deadline.', [['type' => 'text', 'content' => 'A dispute was opened on {{quest.title}}.']]],
            ['payout_processed', 'payout.processed', 'Payout processed', 'Your payout of {{payout.amount}} is on the way', 'Your payout has been processed.', [['type' => 'text', 'content' => 'Your payout of {{payout.amount}} has been processed.']]],
        ];

        foreach ($templates as [$key, $event, $name, $subject, $preheader, $blocks]) {
            DB::table('email_templates')->insert([
                'key' => $key,
                'trigger_event' => $event,
                'name' => $name,
                'subject' => $subject,
                'preheader' => $preheader,
                'blocks' => json_encode($blocks),
                'theme' => json_encode(['logo' => 'HS', 'primary_color' => '#0f766e', 'footer' => 'HustleSafe - Escrow-first marketplace']),
                'variables' => json_encode($this->variablesFor($event)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sections = [
            'Getting Started',
            'Posting a Quest',
            'Submitting Proposals',
            'Payments and Escrow',
            'Disputes',
            'Account and Verification',
            'Platform Policies',
        ];

        foreach ($sections as $i => $section) {
            DB::table('help_sections')->insert([
                'title' => $section,
                'slug' => str($section)->slug(),
                'display_order' => $i + 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_search_logs');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('help_faq_items');
        Schema::dropIfExists('help_sections');
        Schema::dropIfExists('announcement_banners');
        Schema::dropIfExists('email_template_analytics');
        Schema::dropIfExists('email_template_versions');
        Schema::dropIfExists('email_templates');
    }

    private function variablesFor(string $event): array
    {
        $base = [
            ['token' => '{{user.first_name}}', 'description' => 'Recipient first name'],
            ['token' => '{{user.name}}', 'description' => 'Recipient full name'],
            ['token' => '{{app.name}}', 'description' => 'Platform name'],
        ];

        return match ($event) {
            'quest.posted', 'proposal.received', 'dispute.opened' => array_merge($base, [
                ['token' => '{{quest.title}}', 'description' => 'Quest title'],
                ['token' => '{{quest.reference}}', 'description' => 'Quest reference code'],
                ['token' => '{{freelancer.name}}', 'description' => 'Freelancer name when available'],
            ]),
            'payout.processed' => array_merge($base, [
                ['token' => '{{payout.amount}}', 'description' => 'Formatted payout amount'],
                ['token' => '{{payout.reference}}', 'description' => 'Payout reference'],
            ]),
            default => array_merge($base, [
                ['token' => '{{verification.url}}', 'description' => 'Signed verification URL'],
            ]),
        };
    }
};
