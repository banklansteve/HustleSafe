<?php

namespace App\Services\Admin\QuestPatrol;

use App\Models\ProposalReferenceTemplate;
use App\Models\QuestOffer;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ProposalTemplateService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromProposal(QuestOffer $proposal, User $admin, array $data): ProposalReferenceTemplate
    {
        $proposal->loadMissing(['quest.questCategory', 'freelancer']);

        $body = $this->anonymize((string) $proposal->pitch, $proposal->freelancer?->name);

        return ProposalReferenceTemplate::query()->create([
            'title' => (string) ($data['title'] ?? 'Reference: '.Str::limit(strip_tags((string) $proposal->quest?->title), 60)),
            'quest_category_id' => $proposal->quest?->quest_category_id,
            'body' => $body,
            'source_proposal_id' => $proposal->id,
            'created_by_id' => $admin->id,
            'status' => 'published',
            'quality_rating' => $proposal->admin_quality_rating,
            'meta' => [
                'quest_id' => $proposal->quest_id,
                'anonymized' => true,
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function published(int $limit = 20): array
    {
        if (! Schema::hasTable('proposal_reference_templates')) {
            return [];
        }

        return ProposalReferenceTemplate::query()
            ->where('status', 'published')
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProposalReferenceTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'body_excerpt' => Str::limit(strip_tags($template->body), 180),
                'quality_rating' => $template->quality_rating,
                'created_by' => $template->creator?->name,
                'created_at' => $template->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function anonymize(string $pitch, ?string $freelancerName): string
    {
        $text = strip_tags($pitch);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\w.-]+@[\w.-]+\.\w+/', '[email]', $text) ?? $text;
        $text = preg_replace('/\+?\d[\d\s()-]{8,}\d/', '[phone]', $text) ?? $text;

        if ($freelancerName) {
            $text = str_ireplace($freelancerName, '[Freelancer]', $text);
            foreach (preg_split('/\s+/', trim($freelancerName)) ?: [] as $part) {
                if (strlen($part) > 2) {
                    $text = str_ireplace($part, '[Freelancer]', $text);
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
