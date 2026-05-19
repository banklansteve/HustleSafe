<?php

namespace App\Services\Admin;

use App\Jobs\SendEmailBroadcastJob;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\EmailBroadcastTemplate;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailBroadcastService
{
    public function __construct(private readonly AdminActivityLogger $activity) {}

    public function indexPayload(Request $request): array
    {
        return [
            'templates' => $this->templates(),
            'history' => $this->history(),
            'scheduled' => $this->scheduled(),
            'options' => $this->options(),
            'stats' => [
                'total_sent' => EmailBroadcast::query()->whereIn('status', ['sent', 'completed'])->count(),
                'scheduled' => EmailBroadcast::query()->where('status', 'scheduled')->count(),
                'templates' => count($this->systemTemplates()) + EmailBroadcastTemplate::query()->count(),
            ],
        ];
    }

    public function previewAudience(array $audience): array
    {
        $query = $this->audienceQuery($audience);
        $count = (clone $query)->count();

        return [
            'count' => $count,
            'description' => $this->audienceDescription($audience),
        ];
    }

    public function createBroadcast(array $data, User $admin, Request $request): EmailBroadcast
    {
        $audience = $data['audience'] ?? [];
        $users = $this->audienceQuery($audience)->get(['id', 'email', 'first_name', 'last_name', 'name', 'verification_tier']);
        $scheduledFor = ($data['send_mode'] ?? 'now') === 'schedule' ? ($data['scheduled_for'] ?? null) : null;

        return DB::transaction(function () use ($data, $admin, $request, $audience, $users, $scheduledFor): EmailBroadcast {
            $broadcast = EmailBroadcast::query()->create([
                'template_id' => $data['template_id'] ?? null,
                'created_by_admin_id' => $admin->id,
                'subject' => $data['subject'],
                'preview_text' => $data['preview_text'] ?? null,
                'reply_to' => $data['reply_to'] ?? config('mail.from.address'),
                'from_name' => $data['from_name'] ?? config('app.name'),
                'body_html' => $this->ensureUnsubscribe($data['body_html']),
                'audience' => $audience,
                'audience_description' => $this->audienceDescription($audience),
                'status' => $scheduledFor ? 'scheduled' : 'queued',
                'total_recipients' => $users->count(),
                'queued_count' => $users->count(),
                'scheduled_for' => $scheduledFor,
            ]);

            $users->chunk(500)->each(function (Collection $chunk) use ($broadcast): void {
                EmailBroadcastRecipient::query()->insert($chunk->map(fn (User $user) => [
                    'email_broadcast_id' => $broadcast->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'queued',
                    'queued_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });

            $this->activity->log($admin, 'admin.email_broadcast.created', EmailBroadcast::class, $broadcast->id, [
                'subject' => $broadcast->subject,
                'audience' => $audience,
                'audience_description' => $broadcast->audience_description,
                'total_recipients' => $broadcast->total_recipients,
                'scheduled_for' => $broadcast->scheduled_for?->toIso8601String(),
            ], $request);

            if (! $scheduledFor) {
                SendEmailBroadcastJob::dispatch($broadcast->id);
            }

            return $broadcast;
        });
    }

    public function sendTest(array $data, User $admin): void
    {
        $html = $this->renderForUser($this->wrapHtml($this->ensureUnsubscribe($data['body_html']), $data['preview_text'] ?? ''), $admin);
        Mail::html($html, function ($message) use ($data, $admin): void {
            $message->to($admin->email)
                ->subject('[Test] '.$data['subject'])
                ->from(config('mail.from.address'), $data['from_name'] ?? config('app.name'))
                ->replyTo($data['reply_to'] ?? config('mail.from.address'));
        });
    }

    public function createTemplate(array $data, User $admin): EmailBroadcastTemplate
    {
        return EmailBroadcastTemplate::query()->create([
            ...$data,
            'created_by_admin_id' => $admin->id,
            'is_system' => false,
        ]);
    }

    public function updateTemplate(EmailBroadcastTemplate $template, array $data): EmailBroadcastTemplate
    {
        abort_if($template->is_system, 403);
        $template->update($data);

        return $template->refresh();
    }

    public function duplicateTemplate(array $template, User $admin): EmailBroadcastTemplate
    {
        return $this->createTemplate([
            'name' => ($template['name'] ?? 'Template').' Copy',
            'category' => $template['category'] ?? 'Custom',
            'suggested_audience' => $template['suggested_audience'] ?? null,
            'subject' => $template['subject'] ?? '',
            'preview_text' => $template['preview_text'] ?? '',
            'body_html' => $template['body_html'] ?? '',
        ], $admin);
    }

    public function renderForUser(string $html, User $user): string
    {
        $tokens = [
            '{{user.first_name}}' => $user->first_name ?: Str::before($user->name ?? '', ' '),
            '{{user.last_name}}' => $user->last_name ?: Str::after($user->name ?? '', ' '),
            '{{user.email}}' => $user->email,
            '{{user.verification_tier}}' => (string) ($user->verification_tier ?? $user->current_verification_level ?? 'Tier 0'),
            '{{platform.name}}' => config('app.name'),
            '{{platform.url}}' => config('app.url'),
            '{{unsubscribe_link}}' => url('/notifications/unsubscribe?email='.urlencode($user->email)),
        ];

        return strtr($html, $tokens);
    }

    public function wrapHtml(string $bodyHtml, string $previewText = ''): string
    {
        $appName = e(config('app.name'));
        $support = e(config('mail.from.address'));

        return <<<HTML
<!doctype html>
<html><body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
<span style="display:none!important;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">{$previewText}</span>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;">
<tr><td style="background:#0ea5a4;color:#ffffff;text-align:center;padding:22px 16px;border-radius:24px 24px 0 0;font-weight:800;font-size:22px;">{$appName}</td></tr>
<tr><td style="background:#ffffff;padding:32px;border:1px solid #e2e8f0;border-top:0;">{$bodyHtml}</td></tr>
<tr><td style="padding:18px 10px;text-align:center;color:#64748b;font-size:12px;line-height:20px;">{$appName} · {$support}<br>{{unsubscribe_link}}</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
    }

    private function templates(): array
    {
        $custom = EmailBroadcastTemplate::query()->with('creator:id,name,email')->latest()->get()->map(fn (EmailBroadcastTemplate $template) => [
            'id' => $template->id,
            'key' => 'custom-'.$template->id,
            'name' => $template->name,
            'category' => $template->category,
            'suggested_audience' => $template->suggested_audience,
            'subject' => $template->subject,
            'preview_text' => $template->preview_text,
            'body_html' => $template->body_html,
            'is_system' => false,
            'creator' => $template->creator?->name,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ])->all();

        return [...$this->systemTemplates(), ...$custom];
    }

    private function history(): array
    {
        return EmailBroadcast::query()->with('creator:id,name,email')->latest()->limit(50)->get()->map(fn (EmailBroadcast $broadcast) => [
            'id' => $broadcast->id,
            'subject' => $broadcast->subject,
            'audience_description' => $broadcast->audience_description,
            'total_recipients' => $broadcast->total_recipients,
            'status' => $broadcast->status,
            'sent_at' => $broadcast->sent_at?->toIso8601String() ?? $broadcast->created_at?->toIso8601String(),
            'creator' => $broadcast->creator?->name,
            'stats' => [
                'sent' => $broadcast->sent_count,
                'delivered' => $broadcast->delivered_count,
                'opened' => $broadcast->opened_count,
                'clicked' => $broadcast->clicked_count,
                'bounced' => $broadcast->bounced_count,
                'unsubscribed' => $broadcast->unsubscribed_count,
            ],
            'replay' => [
                'audience' => $broadcast->audience,
                'subject' => $broadcast->subject,
                'preview_text' => $broadcast->preview_text,
                'body_html' => $broadcast->body_html,
                'reply_to' => $broadcast->reply_to,
                'from_name' => $broadcast->from_name,
            ],
        ])->all();
    }

    private function scheduled(): array
    {
        return EmailBroadcast::query()->where('status', 'scheduled')->orderBy('scheduled_for')->limit(30)->get(['id', 'subject', 'audience_description', 'total_recipients', 'scheduled_for'])->map(fn (EmailBroadcast $broadcast) => [
            'id' => $broadcast->id,
            'subject' => $broadcast->subject,
            'audience_description' => $broadcast->audience_description,
            'total_recipients' => $broadcast->total_recipients,
            'scheduled_for' => $broadcast->scheduled_for?->toIso8601String(),
        ])->all();
    }

    private function options(): array
    {
        return [
            'states' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $state) => ['value' => $state->id, 'label' => $state->name])->values(),
            'categories' => QuestCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn (QuestCategory $category) => ['value' => $category->id, 'label' => $category->name])->values(),
            'tokens' => ['{{user.first_name}}', '{{user.last_name}}', '{{user.email}}', '{{user.verification_tier}}', '{{platform.name}}', '{{platform.url}}', '{{unsubscribe_link}}'],
        ];
    }

    private function audienceQuery(array $audience): Builder
    {
        $query = User::query()->whereNotNull('email');
        $groups = $audience['groups'] ?? ['all_users'];
        if (! in_array('all_users', $groups, true)) {
            $query->where(function (Builder $sub) use ($groups): void {
                if (in_array('clients', $groups, true)) {
                    $sub->orWhere('account_type', 'client');
                }
                if (in_array('freelancers', $groups, true)) {
                    $sub->orWhere('account_type', 'freelancer');
                }
                if (in_array('admins', $groups, true)) {
                    $sub->orWhereHas('role', fn (Builder $role) => $role->whereIn('slug', ['admin', 'super_admin']));
                }
                if (in_array('super_admins', $groups, true)) {
                    $sub->orWhereHas('role', fn (Builder $role) => $role->where('slug', 'super_admin'));
                }
            });
        }
        if (! empty($audience['state_ids'])) {
            $query->whereIn('state_id', $audience['state_ids']);
        }
        if (! empty($audience['category_ids'])) {
            $query->whereIn('id', DB::table('freelancer_quest_category')
                ->whereIn('quest_category_id', $audience['category_ids'])
                ->select('user_id'));
        }
        if (! empty($audience['verification_tiers'])) {
            $query->whereIn('verification_tier', $audience['verification_tiers']);
        }
        if (($audience['account_status'] ?? 'active') === 'active') {
            $query->whereNull('suspended_at')->whereNull('banned_at');
        } elseif (($audience['account_status'] ?? '') === 'suspended') {
            $query->whereNotNull('suspended_at');
        } elseif (($audience['account_status'] ?? '') === 'pending_verification') {
            $query->whereNull('kyc_verified_at');
        }
        if (($audience['activity'] ?? '') === 'active_30') {
            $query->where('last_active_at', '>=', now()->subDays(30));
        } elseif (($audience['activity'] ?? '') === 'inactive_60') {
            $query->where(fn (Builder $sub) => $sub->whereNull('last_active_at')->orWhere('last_active_at', '<', now()->subDays(60)));
        } elseif (($audience['activity'] ?? '') === 'completed_contract') {
            $query->where(fn (Builder $sub) => $sub
                ->whereHas('questsAsClient', fn (Builder $quest) => $quest->where('status', 'completed'))
                ->orWhereHas('questsAsFreelancer', fn (Builder $quest) => $quest->where('status', 'completed')));
        } elseif (($audience['activity'] ?? '') === 'never_started_contract') {
            $query->whereDoesntHave('questsAsClient')->whereDoesntHave('questsAsFreelancer');
        }

        return $query;
    }

    private function audienceDescription(array $audience): string
    {
        return collect([
            collect($audience['groups'] ?? ['all_users'])->map(fn ($group) => Str::headline($group))->join(', '),
            ! empty($audience['state_ids']) ? 'selected Nigerian states' : null,
            ! empty($audience['verification_tiers']) ? 'tiers '.implode(', ', $audience['verification_tiers']) : null,
            $audience['account_status'] ?? null,
            $audience['activity'] ?? null,
        ])->filter()->join(' · ');
    }

    private function ensureUnsubscribe(string $html): string
    {
        return str_contains($html, '{{unsubscribe_link}}')
            ? $html
            : $html.'<p style="font-size:12px;color:#64748b;margin-top:24px;">Manage email preferences: <a href="{{unsubscribe_link}}">unsubscribe</a>.</p>';
    }

    private function systemTemplates(): array
    {
        $items = [
            'Welcome & Onboarding' => ['Welcome to the Platform', 'Complete Your Profile', 'Verify Your Identity', 'Verification Approved'],
            'Quest & Proposal Activity' => ['Your Quest Is Live', 'New Proposals on Your Quest', 'Your Proposal Was Accepted', 'Quest Expiring Soon', 'No Proposals Yet'],
            'Payments & Escrow' => ['Payment Received Into Escrow', 'Milestone Delivered', 'Payout Processed', 'Payout Failed', 'Refund Issued'],
            'Disputes & Resolution' => ['Dispute Opened', 'Dispute Ruling Issued', 'Dispute Resolved'],
            'Admin Actions' => ['Quest Under Admin Review', 'Quest Suspended', 'Quest Reinstated', 'Proposal Removed', 'Account Warning Issued', 'Account Suspended', 'Account Reinstated'],
            'Growth & Engagement' => ['We Miss You', 'New Categories Available', 'Platform Maintenance Scheduled', 'New Feature Announcement'],
            'Trust & Safety' => ['Suspicious Activity Alert', 'Password Changed Confirmation', 'New Device Login Alert', 'Data Request Received'],
        ];

        return collect($items)->flatMap(fn (array $names, string $category) => collect($names)->map(fn (string $name) => [
            'id' => null,
            'key' => 'system-'.Str::slug($category.'-'.$name),
            'name' => $name,
            'category' => $category,
            'suggested_audience' => Str::contains($name, ['Quest', 'Client']) ? 'Clients' : 'Targeted users',
            'subject' => $name.' from {{platform.name}}',
            'preview_text' => 'A quick update from {{platform.name}}.',
            'body_html' => '<h2>'.$name.'</h2><p>Hello {{user.first_name}},</p><p>This is a branded HustleSafe update about '.e(Str::lower($name)).'.</p><p><a href="{{platform.url}}" style="display:inline-block;background:#0ea5a4;color:#ffffff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:700;">Open HustleSafe</a></p>',
            'is_system' => true,
            'creator' => 'System',
            'updated_at' => null,
        ]))->values()->all();
    }
}
