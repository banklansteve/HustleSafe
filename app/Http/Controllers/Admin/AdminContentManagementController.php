<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementBannerRequest;
use App\Http\Requests\Admin\StoreHelpFaqRequest;
use App\Http\Requests\Admin\StoreHelpSectionRequest;
use App\Http\Requests\Admin\UpdateEmailTemplateRequest;
use App\Models\AnnouncementBanner;
use App\Models\EmailTemplate;
use App\Models\HelpFaqItem;
use App\Models\HelpSection;
use App\Services\Admin\ContentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentManagementController extends Controller
{
    public function __construct(private readonly ContentManagementService $content) {}

    public function index(Request $request): Response
    {
        $section = (string) $request->query('tab', $request->query('section', 'email'));
        if (! in_array($section, ['email', 'announcements', 'help'], true)) {
            $section = 'email';
        }

        return Inertia::render('Admin/Content/Index', [
            'section' => $section,
            'content' => fn () => [
                'email' => $this->content->emailTemplates(),
                'announcements' => $this->content->announcements($request),
                'help' => $this->content->helpContent(),
                'searchGaps' => $this->content->searchGaps(),
            ],
            'filters' => $request->only(['status']),
        ]);
    }

    public function showTemplate(EmailTemplate $template): JsonResponse
    {
        return response()->json($this->content->templatePayload($template));
    }

    public function updateTemplate(UpdateEmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        $updated = $this->content->updateTemplate($template, $request->user(), $request->validated());

        return response()->json($this->content->templatePayload($updated));
    }

    public function restoreTemplate(Request $request, EmailTemplate $template, int $version): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $updated = $this->content->restoreTemplateVersion($template, $version, $request->user());

        return response()->json($this->content->templatePayload($updated));
    }

    public function testTemplate(Request $request, EmailTemplate $template): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->content->sendTest($template, $data['email']);

        return response()->json(['ok' => true]);
    }

    public function storeAnnouncement(StoreAnnouncementBannerRequest $request): RedirectResponse
    {
        $this->content->saveAnnouncement($request->validated(), $request->user());

        return back()->with('success', 'Announcement banner saved.');
    }

    public function updateAnnouncement(StoreAnnouncementBannerRequest $request, AnnouncementBanner $banner): RedirectResponse
    {
        $this->content->saveAnnouncement($request->validated(), $request->user(), $banner);

        return back()->with('success', 'Announcement banner updated.');
    }

    public function archiveAnnouncement(AnnouncementBanner $banner): RedirectResponse
    {
        $banner->update(['status' => 'archived']);

        return back()->with('success', 'Announcement banner archived.');
    }

    public function storeHelpSection(StoreHelpSectionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        HelpSection::query()->create($data + [
            'slug' => Str::slug((string) $data['title']),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => $data['status'] ?? 'active',
        ]);

        return back()->with('success', 'Help section created.');
    }

    public function storeFaq(StoreHelpFaqRequest $request): RedirectResponse
    {
        $this->content->saveFaq($request->validated(), $request->user());

        return back()->with('success', 'FAQ saved.');
    }

    public function updateFaq(StoreHelpFaqRequest $request, HelpFaqItem $faq): RedirectResponse
    {
        $this->content->saveFaq($request->validated(), $request->user(), $faq);

        return back()->with('success', 'FAQ updated.');
    }

    public function restoreFaq(Request $request, HelpFaqItem $faq, int $version): RedirectResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $this->content->restoreFaq($faq, $version, $request->user());

        return back()->with('success', 'FAQ version restored.');
    }

    public function archiveFaq(HelpFaqItem $faq): RedirectResponse
    {
        $faq->update(['status' => 'archived']);

        return back()->with('success', 'FAQ archived.');
    }
}
