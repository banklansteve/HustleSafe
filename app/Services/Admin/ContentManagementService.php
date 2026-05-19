<?php

namespace App\Services\Admin;

use App\Mail\EmailTemplateTestMail;
use App\Models\AnnouncementBanner;
use App\Models\ContentVersion;
use App\Models\EmailTemplate;
use App\Models\HelpFaqItem;
use App\Models\HelpSearchLog;
use App\Models\HelpSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContentManagementService
{
    public function dashboard(Request $request, string $section): array
    {
        return [
            'email' => $section === 'email' ? $this->emailTemplates() : null,
            'announcements' => $section === 'announcements' ? $this->announcements($request) : null,
            'help' => $section === 'help' ? $this->helpContent() : null,
            'searchGaps' => $section === 'help' ? $this->searchGaps() : [],
        ];
    }

    public function emailTemplates(): array
    {
        return [
            'templates' => EmailTemplate::query()
                ->with('editor:id,name,email')
                ->with(['analytics' => fn ($q) => $q->where('metric_date', '>=', now()->subDays(30))])
                ->orderBy('trigger_event')
                ->get()
                ->map(fn (EmailTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'key' => $template->key,
                    'trigger_event' => $template->trigger_event,
                    'subject' => $template->subject,
                    'last_edited_at' => $template->updated_at?->toIso8601String(),
                    'last_edited_by' => $template->editor?->name ?? 'System',
                    'analytics' => $this->templateAnalytics($template),
                ]),
            'provider' => $this->emailProvider(),
        ];
    }

    public function templatePayload(EmailTemplate $template): array
    {
        $template->load(['versions.creator:id,name,email', 'analytics']);

        return [
            'id' => $template->id,
            'key' => $template->key,
            'trigger_event' => $template->trigger_event,
            'name' => $template->name,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'blocks' => $template->blocks ?? [],
            'theme' => $template->theme ?? [],
            'variables' => $template->variables ?? [],
            'preview_html' => $this->renderTemplate($template),
            'versions' => $template->versions->take(10)->map(fn ($version) => [
                'id' => $version->id,
                'subject' => $version->subject,
                'created_at' => $version->created_at?->toIso8601String(),
                'created_by' => $version->creator?->name,
                'change_note' => $version->change_note,
            ])->values(),
            'analytics' => $this->templateAnalytics($template),
        ];
    }

    public function updateTemplate(EmailTemplate $template, User $admin, array $data): EmailTemplate
    {
        $template->versions()->create([
            'created_by' => $admin->id,
            'subject' => $template->subject,
            'preheader' => $template->preheader,
            'blocks' => $template->blocks,
            'theme' => $template->theme,
            'variables' => $template->variables,
            'change_note' => $data['change_note'] ?? 'Saved from editor',
        ]);

        $template->update([
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'blocks' => $data['blocks'],
            'theme' => $data['theme'] ?? $template->theme,
            'last_edited_by' => $admin->id,
        ]);

        $this->trimEmailVersions($template);

        return $template->fresh();
    }

    public function restoreTemplateVersion(EmailTemplate $template, int $versionId, User $admin): EmailTemplate
    {
        $version = $template->versions()->findOrFail($versionId);

        return $this->updateTemplate($template, $admin, [
            'subject' => $version->subject,
            'preheader' => $version->preheader,
            'blocks' => $version->blocks ?? [],
            'theme' => $version->theme ?? [],
            'change_note' => 'Restored version #'.$version->id,
        ]);
    }

    public function sendTest(EmailTemplate $template, string $email): void
    {
        Mail::to($email)->send(new EmailTemplateTestMail(
            $this->replaceVariables($template->subject),
            $this->renderTemplate($template),
        ));
    }

    public function announcements(Request $request): array
    {
        $query = AnnouncementBanner::query()->with(['creator:id,name,email', 'updater:id,name,email'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return [
            'banners' => $query->paginate(20)->withQueryString()->through(fn (AnnouncementBanner $banner) => [
                'id' => $banner->id,
                'message' => $banner->message,
                'link_url' => $banner->link_url,
                'link_text' => $banner->link_text,
                'color' => $banner->color,
                'segment' => $banner->segment,
                'starts_at' => $banner->starts_at?->toIso8601String(),
                'ends_at' => $banner->ends_at?->toIso8601String(),
                'dismissible' => $banner->dismissible,
                'status' => $this->bannerStatus($banner),
                'created_by' => $banner->creator?->name,
            ]),
        ];
    }

    public function saveAnnouncement(array $data, User $admin, ?AnnouncementBanner $banner = null): AnnouncementBanner
    {
        $this->assertNoBannerConflict($data, $banner);
        $payload = array_merge($data, [
            'created_by' => $banner?->created_by ?? $admin->id,
            'updated_by' => $admin->id,
            'status' => $data['status'] ?? 'active',
        ]);

        if ($banner) {
            $banner->update($payload);

            return $banner->fresh();
        }

        return AnnouncementBanner::query()->create($payload);
    }

    public function helpContent(): array
    {
        return [
            'sections' => HelpSection::query()
                ->with(['faqs.editor:id,name,email'])
                ->orderBy('display_order')
                ->get()
                ->map(fn (HelpSection $section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'slug' => $section->slug,
                    'display_order' => $section->display_order,
                    'status' => $section->status,
                    'faqs' => $section->faqs->map(fn (HelpFaqItem $faq) => $this->faqPayload($faq))->values(),
                ]),
        ];
    }

    public function saveFaq(array $data, User $admin, ?HelpFaqItem $faq = null): HelpFaqItem
    {
        if ($faq) {
            $this->snapshotFaq($faq, $admin, $data['change_note'] ?? 'Saved FAQ');
            $faq->update($data + ['last_edited_by' => $admin->id]);
            $this->trimContentVersions($faq);

            return $faq->fresh();
        }

        $faq = HelpFaqItem::query()->create($data + ['last_edited_by' => $admin->id]);
        $this->snapshotFaq($faq, $admin, 'Initial FAQ version');

        return $faq;
    }

    public function restoreFaq(HelpFaqItem $faq, int $versionId, User $admin): HelpFaqItem
    {
        $version = $faq->versions()->findOrFail($versionId);
        $snapshot = $version->snapshot;

        return $this->saveFaq([
            'help_section_id' => $snapshot['help_section_id'],
            'question' => $snapshot['question'],
            'answer' => $snapshot['answer'],
            'audience' => $snapshot['audience'],
            'search_keywords' => $snapshot['search_keywords'] ?? [],
            'display_order' => $snapshot['display_order'],
            'status' => $snapshot['status'],
            'change_note' => 'Restored version #'.$version->id,
        ], $admin, $faq);
    }

    public function activeBannerFor(?User $user): ?array
    {
        $segments = ['all'];
        if ($user) {
            $role = $user->role?->slug;
            if ($role === 'client') {
                $segments[] = 'clients';
            }
            if ($role === 'freelancer') {
                $segments[] = 'freelancers';
            }
            if (($user->kyc_tier ?? $user->verification_tier ?? 0) < 1) {
                $segments[] = 'unverified';
            }
        }

        $banner = AnnouncementBanner::query()
            ->where('status', 'active')
            ->whereIn('segment', $segments)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByRaw("field(segment, 'unverified', 'clients', 'freelancers', 'all')")
            ->latest()
            ->first();

        return $banner ? [
            'id' => $banner->id,
            'message' => $banner->message,
            'link_url' => $banner->link_url,
            'link_text' => $banner->link_text,
            'color' => $banner->color,
            'segment' => $banner->segment,
            'dismissible' => $banner->dismissible,
        ] : null;
    }

    public function searchGaps(): array
    {
        return HelpSearchLog::query()
            ->where('results_count', 0)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('query, count(*) as total, max(created_at) as last_seen_at')
            ->groupBy('query')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'query' => $row->query,
                'total' => (int) $row->total,
                'last_seen_at' => $row->last_seen_at,
            ])
            ->all();
    }

    public function renderTemplate(EmailTemplate $template): string
    {
        $theme = $template->theme ?? [];
        $primary = e($theme['primary_color'] ?? '#0f766e');
        $logo = e($theme['logo'] ?? 'HS');
        $footer = e($theme['footer'] ?? config('app.name'));
        $body = collect($template->blocks ?? [])->map(fn ($block) => $this->renderBlock($block, $primary))->implode('');

        return <<<HTML
        <div style="background:#f8fafc;padding:32px;font-family:Inter,Arial,sans-serif;color:#0f172a">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0">
                <div style="padding:24px;background:{$primary};color:#ffffff;font-weight:900;font-size:20px">{$logo}</div>
                <div style="padding:28px">{$body}</div>
                <div style="padding:20px 28px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px">{$footer}</div>
            </div>
        </div>
        HTML;
    }

    private function renderBlock(array $block, string $primary): string
    {
        $type = $block['type'] ?? 'text';
        if ($type === 'button') {
            $label = e($this->replaceVariables((string) ($block['label'] ?? 'Open')));
            $url = e($this->replaceVariables((string) ($block['url'] ?? '#')));

            return "<p style=\"margin:24px 0\"><a href=\"{$url}\" style=\"display:inline-block;background:{$primary};color:#fff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:800\">{$label}</a></p>";
        }
        if ($type === 'divider') {
            return '<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0" />';
        }
        if ($type === 'image') {
            $url = e((string) ($block['url'] ?? ''));

            return $url ? "<img src=\"{$url}\" style=\"max-width:100%;border-radius:16px;margin:16px 0\" />" : '';
        }

        $content = nl2br(e($this->replaceVariables((string) ($block['content'] ?? ''))));

        return "<p style=\"font-size:15px;line-height:1.7;margin:0 0 16px\">{$content}</p>";
    }

    private function replaceVariables(string $text): string
    {
        $map = [
            '{{user.first_name}}' => 'Ada',
            '{{user.name}}' => 'Ada Okonkwo',
            '{{app.name}}' => config('app.name', 'HustleSafe'),
            '{{verification.url}}' => url('/verify-email/example'),
            '{{quest.title}}' => 'Paint a three-bedroom apartment',
            '{{quest.reference}}' => 'HSQ-DEMO123',
            '{{freelancer.name}}' => 'Tunde Bello',
            '{{payout.amount}}' => '₦125,000.00',
            '{{payout.reference}}' => 'PAY-DEMO123',
        ];

        return strtr($text, $map);
    }

    private function templateAnalytics(EmailTemplate $template): array
    {
        $rows = $template->analytics ?? collect();
        $sent = max(1, (int) $rows->sum('sent_count'));
        $open = round(((int) $rows->sum('open_count') / $sent) * 100, 1);
        $click = round(((int) $rows->sum('click_count') / $sent) * 100, 1);
        $unsub = round(((int) $rows->sum('unsubscribe_count') / $sent) * 100, 1);

        return [
            'open_rate' => $open,
            'click_rate' => $click,
            'unsubscribe_rate' => $unsub,
            'needs_attention' => $sent > 1 && $open < 20,
        ];
    }

    private function emailProvider(): string
    {
        return config('services.mailgun.domain') ? 'Mailgun' : (config('services.sendgrid.key') ? 'SendGrid' : config('mail.default', 'mail'));
    }

    private function assertNoBannerConflict(array $data, ?AnnouncementBanner $ignore = null): void
    {
        $starts = isset($data['starts_at']) && $data['starts_at'] ? Carbon::parse($data['starts_at']) : now()->subYears(10);
        $ends = isset($data['ends_at']) && $data['ends_at'] ? Carbon::parse($data['ends_at']) : now()->addYears(10);

        $conflict = AnnouncementBanner::query()
            ->where('segment', $data['segment'])
            ->where('status', 'active')
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $starts))
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<', $ends))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['segment' => 'There is already an active banner for this user segment during that time.']);
        }
    }

    private function bannerStatus(AnnouncementBanner $banner): string
    {
        if ($banner->status !== 'active') {
            return $banner->status;
        }
        if ($banner->starts_at && $banner->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($banner->ends_at && $banner->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function faqPayload(HelpFaqItem $faq): array
    {
        $faq->loadMissing('versions.creator:id,name,email');

        return [
            'id' => $faq->id,
            'help_section_id' => $faq->help_section_id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'audience' => $faq->audience,
            'search_keywords' => $faq->search_keywords ?? [],
            'display_order' => $faq->display_order,
            'status' => $faq->status,
            'last_edited_by' => $faq->editor?->name,
            'updated_at' => $faq->updated_at?->toIso8601String(),
            'versions' => $faq->versions->take(10)->map(fn (ContentVersion $version) => [
                'id' => $version->id,
                'created_at' => $version->created_at?->toIso8601String(),
                'created_by' => $version->creator?->name,
                'change_note' => $version->change_note,
            ])->values(),
        ];
    }

    private function snapshotFaq(HelpFaqItem $faq, User $admin, string $note): void
    {
        $faq->versions()->create([
            'created_by' => $admin->id,
            'snapshot' => $faq->only(['help_section_id', 'question', 'answer', 'audience', 'search_keywords', 'display_order', 'status']),
            'change_note' => $note,
        ]);
    }

    private function trimEmailVersions(EmailTemplate $template): void
    {
        $template->versions()->skip(10)->take(PHP_INT_MAX)->get()->each->delete();
    }

    private function trimContentVersions(HelpFaqItem $faq): void
    {
        $faq->versions()->skip(10)->take(PHP_INT_MAX)->get()->each->delete();
    }
}
