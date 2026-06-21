<?php

namespace App\Services\Quest;

use App\Models\QuestCategory;

/**
 * In-app quest description drafts — no external API required.
 *
 * Descriptions focus on work scope and outcomes only. Logistics the create
 * wizard already collects elsewhere (location, budget, schedule, materials,
 * access, preferences) are intentionally omitted.
 */
final class QuestDescriptionTemplateService
{
    public function __construct(
        private readonly QuestPreferenceProfileService $profiles,
    ) {}

    /**
     * @return list<array{label: string, text: string}>
     */
    public function suggest(string $title, ?int $categoryId): array
    {
        $category = $categoryId !== null && $categoryId > 0
            ? QuestCategory::query()->with('parent:id,name,slug')->find($categoryId)
            : null;

        $profile = $this->profiles->profileForLeafCategoryId($categoryId);
        $type = (string) ($profile['profile_type'] ?? 'none');

        $task = trim($title) !== '' ? trim($title) : 'this job';
        $leafName = $category?->parent_id !== null ? (string) $category->name : '';

        return match ($type) {
            'physical' => $this->physicalTemplates($task, $leafName),
            'care' => $this->careTemplates($task, $leafName),
            'logistics' => $this->logisticsTemplates($task, $leafName),
            'lessons' => $this->lessonsTemplates($task, $leafName),
            'design' => $this->designTemplates($task, $leafName),
            'technical' => $this->technicalTemplates($task, $leafName),
            'professional' => $this->professionalTemplates($task, $leafName),
            default => $this->genericTemplates($task, $leafName),
        };
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function physicalTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " for {$leafName}" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "I need{$context}: {$task}.\n\nThe space is in [current condition]. Please cover [list the main tasks — e.g. all rooms, specific fixtures, or problem areas]. Finish should be [standard you expect]. Let me know if anything in the brief needs clarifying before you quote.",
            ],
            [
                'label' => 'Detailed',
                'text' => "Job overview\n{$task}{$context}.\n\nWhat needs doing\n- [Room/area 1]: [current state] → [desired outcome]\n- [Room/area 2]: [specific tasks — scrub, repair, install, treat, etc.]\n- [Any problem spots]: [describe issue and what “fixed” looks like]\n\nQuality & finish\n- [Standard expected — e.g. move-in ready, functional repair, neat edges, no residue]\n- [Photos or checklist you will use to sign off]\n\nIn scope\n- [Everything included in this job]\n\nOut of scope\n- [Work you are NOT asking for in this post — helps avoid surprise quotes]",
            ],
            [
                'label' => 'Structured',
                'text' => "Overview\n{$task}{$context}.\n\nCurrent situation\n- [Brief context — age, last service, why you are posting now]\n\nTasks (in scope)\n1. [Area/task]\n2. [Area/task]\n3. [Area/task]\n\nSuccess criteria\n- [How you will judge the job complete]\n\nExcluded from this job\n- [Optional — work you will handle separately or in a follow-up post]\n\nNotes for quoting\n- [Dimensions, quantities, or access constraints that affect the work itself — not address or timing]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function careTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Care needed{$context}: {$task}.\n\nDaily focus: [meals, hygiene, companionship, mobility support, etc.]. Important routines: [sleep, medication reminders, school pickup]. Please describe how you would handle [specific situation relevant to this post].",
            ],
            [
                'label' => 'Detailed',
                'text' => "Care brief{$context}\n{$task}\n\nWho & context\n- [Age, temperament, mobility, communication needs]\n- [Why you need support now]\n\nDuties expected each visit/shift\n- [List concrete tasks — feeding, bathing, homework help, laundry for client, etc.]\n- [Activities to encourage or avoid]\n\nRoutines to respect\n- [Mealtimes, nap times, screen rules, religious or cultural notes]\n\nSafety & sensitivities\n- [Allergies, triggers, medical notes that affect how work is done]\n\nWhat success looks like\n- [Calm household, child settled, parent returns to tidy home, etc.]",
            ],
            [
                'label' => 'Structured',
                'text' => "Service\n{$task}{$context}\n\nPerson receiving care\n- [Brief profile relevant to the work]\n\nCore responsibilities\n- [Task 1]\n- [Task 2]\n- [Task 3]\n\nHousehold rules\n- [Non-negotiables for how care is delivered]\n\nOut of scope\n- [Tasks you are not asking for in this engagement]\n\nIdeal provider approach\n- [Gentle/firm, proactive communication, experience with similar cases]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function logisticsTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " — {$leafName}" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Move/delivery{$context}: {$task}.\n\nItems: [brief list — e.g. sofa, 15 boxes, fridge]. Condition: [already packed / needs wrapping / disassembly required]. Special handling: [fragile glass, upright only, etc.]. Quote for the full scope described.",
            ],
            [
                'label' => 'Detailed',
                'text' => "Job summary\n{$task}{$context}\n\nWhat is being moved\n- [Item types, approximate count, largest/heaviest pieces]\n- [Packed state — boxed, loose, furniture disassembled or not]\n\nHandling requirements\n- [Fragile, liquids, electronics, artwork, etc.]\n- [Any disassembly/reassembly you expect as part of the job]\n\nScope of work\n- [Load from [floor/room], deliver to [room], unpack or drop only]\n- [Stairs, narrow doors, or other physical constraints that affect labour]\n\nOut of scope\n- [Packing service, storage, disposal — if not part of this job]",
            ],
            [
                'label' => 'Structured',
                'text' => "Overview\n{$task}{$context}\n\nInventory (summary)\n- [Category / qty / notable items]\n\nWork required\n1. [Collect from …]\n2. [Protect/wrap if needed]\n3. [Transport & deliver]\n4. [Place/unpack as specified]\n\nCare instructions\n- [Items that need extra caution]\n\nNot included\n- [Anything outside this move]\n\nSign-off\n- [How you confirm everything arrived intact]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function lessonsTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Lesson goal{$context}: {$task}.\n\nStarting point: [what the learner can already do]. Target: [specific skill or exam outcome]. Topics to cover: [list 3–5]. Please propose a lesson plan for the first few sessions.",
            ],
            [
                'label' => 'Detailed',
                'text' => "Learning brief{$context}\n{$task}\n\nLearner today\n- [Age, level, strengths, gaps]\n- [Past lessons or self-study]\n\nGoals\n- [Short-term — pass test, play a song, lose 5 kg]\n- [Longer-term if relevant]\n\nSyllabus / topics\n- [Module 1: …]\n- [Module 2: …]\n- [Skills to drill — technique, theory, practice pieces]\n\nTeaching style that works\n- [Patient, drill-based, homework between sessions, etc.]\n\nHow we measure progress\n- [Checkpoints, mock tests, performance milestones]",
            ],
            [
                'label' => 'Structured',
                'text' => "Subject\n{$task}{$context}\n\nBaseline\n- [Current ability]\n\nOutcomes\n- [What the learner should be able to do after this engagement]\n\nCurriculum scope\n1. [Topic/skill]\n2. [Topic/skill]\n3. [Topic/skill]\n\nPractice expectations\n- [Between-session work, if any]\n\nOut of scope\n- [Topics you are not asking to cover now]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function designTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Creative brief{$context}: {$task}.\n\nDeliverables: [e.g. logo + social templates]. Use: [where assets will appear]. Direction: [mood, colours, references]. Must include: [copy, dimensions, variants]. Avoid: [styles or clichés you do not want].",
            ],
            [
                'label' => 'Detailed',
                'text' => "Project\n{$task}{$context}\n\nBackground\n- [Brand/project context and why this work is needed]\n\nDeliverables\n- [Asset 1 — size, quantity, use case]\n- [Asset 2 — …]\n\nCreative direction\n- [Tone, audience, references/links]\n- [Must-haves vs nice-to-haves]\n\nContent & specs\n- [Copy to include, languages, aspect ratios, print vs digital]\n\nIn scope\n- [Concept rounds, revisions covered in your quote, source files if needed]\n\nOut of scope\n- [Motion, print production, copywriting — if separate]",
            ],
            [
                'label' => 'Structured',
                'text' => "Brief\n{$task}{$context}\n\nObjective\n- [What this creative work should achieve]\n\nOutputs\n1. [Deliverable + spec]\n2. [Deliverable + spec]\n\nLook & feel\n- [Keywords, references, anti-references]\n\nMandatory elements\n- [Logo lockup, legal line, product photos, etc.]\n\nExcluded\n- [Work not part of this brief]\n\nApproval\n- [What you need to see before calling it done]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function technicalTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Build/fix{$context}: {$task}.\n\nProblem: [what is broken or missing]. Expected behaviour: [how it should work when done]. In scope: [features, pages, or endpoints]. Out of scope: [anything deferred]. Acceptance: [how you will test and sign off].",
            ],
            [
                'label' => 'Detailed',
                'text' => "Technical brief{$context}\n{$task}\n\nContext\n- [Existing product, repo, or greenfield]\n- [Users affected]\n\nRequirements\n- [Functional requirement 1]\n- [Functional requirement 2]\n- [Integrations, APIs, data sources]\n\nAcceptance criteria\n- [Testable conditions for “done”]\n\nConstraints\n- [Performance, security, browser/device, compliance]\n\nIn scope\n- [Work packages included in this post]\n\nOut of scope\n- [Phase 2 items, maintenance, content entry — if excluded]",
            ],
            [
                'label' => 'Structured',
                'text' => "Project\n{$task}{$context}\n\nGoal\n- [Business/user outcome]\n\nScope\n1. [Feature or fix]\n2. [Feature or fix]\n3. [Feature or fix]\n\nDone when\n- [Checklist a freelancer can verify]\n\nKnown context\n- [Stack, repo access, staging — only if it affects the work description]\n\nNot in this phase\n- [Explicit exclusions]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function professionalTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "Engagement{$context}: {$task}.\n\nDecision needed: [what you need to decide or produce]. Audience: [who reads/uses the output]. Depth: [high-level review vs detailed analysis]. Include: [data sources, frameworks, recommendations].",
            ],
            [
                'label' => 'Detailed',
                'text' => "Brief{$context}\n{$task}\n\nSituation\n- [Business context and why help is needed now]\n\nObjective\n- [Specific question to answer or output to produce]\n\nScope of analysis/work\n- [Topics, accounts, markets, or documents in scope]\n- [Method or approach you expect]\n\nDeliverable content\n- [Sections, models, or recommendations required]\n\nQuality bar\n- [Evidence, citations, assumptions stated, actionable next steps]\n\nOut of scope\n- [Legal advice, implementation, ongoing support — if excluded]",
            ],
            [
                'label' => 'Structured',
                'text' => "Request\n{$task}{$context}\n\nBackground\n- [Context]\n\nProblem statement\n- [What must be solved or documented]\n\nWork scope\n1. [Activity/deliverable]\n2. [Activity/deliverable]\n\nInputs available\n- [Docs, data, access you will share after award]\n\nSuccess criteria\n- [How you judge the work complete and useful]\n\nExcluded\n- [Not part of this engagement]",
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function genericTemplates(string $task, string $leafName): array
    {
        $context = $leafName !== '' ? " ({$leafName})" : '';

        return [
            [
                'label' => 'Concise',
                'text' => "{$task}{$context}.\n\nI need [clear outcome]. Please handle [main tasks]. Quality bar: [how I know it is done well]. Mention anything you need clarified before quoting.",
            ],
            [
                'label' => 'Detailed',
                'text' => "Overview\n{$task}{$context}\n\nBackground\n- [Why this work is needed]\n\nWork required\n- [Task 1 — be specific]\n- [Task 2]\n- [Task 3]\n\nSuccess criteria\n- [Measurable or observable outcomes]\n\nIn scope\n- [Included in this job]\n\nOut of scope\n- [Excluded — reduces mismatch on proposals]",
            ],
            [
                'label' => 'Structured',
                'text' => "Job\n{$task}{$context}\n\nContext\n- [Situation the provider should understand]\n\nScope\n1. [Deliverable or task]\n2. [Deliverable or task]\n3. [Deliverable or task]\n\nStandards\n- [Quality, brand, or compliance expectations]\n\nNot included\n- [Optional exclusions]\n\nQuestions welcome\n- [Invite clarifications on ambiguous points]",
            ],
        ];
    }
}
