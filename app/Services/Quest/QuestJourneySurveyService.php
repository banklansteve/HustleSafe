<?php

namespace App\Services\Quest;

use App\Models\Quest;
use App\Models\QuestJourneySurvey;
use App\Models\QuestOffer;
use App\Models\StaffProactiveOutreachItem;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuestJourneySurveyService
{
    public function onQuestFundsReleased(Quest $quest): void
    {
        $quest->loadMissing(['client', 'freelancer']);

        if ($quest->client_id) {
            $this->createAndSchedule(
                quest: $quest,
                userId: (int) $quest->client_id,
                offerId: null,
                cohort: 'client_completed',
            );
        }

        if ($quest->freelancer_id) {
            $this->createAndSchedule(
                quest: $quest,
                userId: (int) $quest->freelancer_id,
                offerId: $quest->accepted_quest_offer_id ? (int) $quest->accepted_quest_offer_id : null,
                cohort: 'freelancer_awarded',
            );
        }
    }

    public function onProposalRejected(Quest $quest, QuestOffer $offer, string $reason = 'declined'): void
    {
        if (! $offer->freelancer_id) {
            return;
        }

        $this->createAndSendImmediately(
            quest: $quest,
            userId: (int) $offer->freelancer_id,
            offerId: (int) $offer->id,
            cohort: 'freelancer_rejected',
            rejectionReason: $reason,
        );
    }

    public function createAndSchedule(
        Quest $quest,
        int $userId,
        ?int $offerId,
        string $cohort,
    ): ?QuestJourneySurvey {
        $survey = $this->createSurveyRecord($quest, $userId, $offerId, $cohort);
        if (! $survey) {
            return null;
        }

        $delayHours = (int) config('quest_journey_survey.email_delay_hours', 2);
        $survey->update(['email_send_at' => now()->addHours($delayHours)]);

        \App\Jobs\SendQuestJourneySurveyMailJob::dispatch($survey->id)
            ->delay($survey->email_send_at);

        return $survey;
    }

    public function createAndSendImmediately(
        Quest $quest,
        int $userId,
        ?int $offerId,
        string $cohort,
        ?string $rejectionReason = null,
    ): ?QuestJourneySurvey {
        $survey = $this->createSurveyRecord($quest, $userId, $offerId, $cohort, $rejectionReason);
        if (! $survey) {
            return null;
        }

        $survey->update(['email_send_at' => now()]);
        \App\Jobs\SendQuestJourneySurveyMailJob::dispatch($survey->id);

        return $survey;
    }

    public function captureFirstAnswer(QuestJourneySurvey $survey, string $answer): QuestJourneySurvey
    {
        if ($survey->isExpired()) {
            throw ValidationException::withMessages(['survey' => [__('This survey has closed.')]]);
        }

        if ($survey->isSubmitted()) {
            return $survey;
        }

        $firstKey = $this->firstQuestionKey($survey->cohort);
        $allowed = collect($this->optionsForQuestionKey($survey->cohort, $firstKey))
            ->pluck('value')
            ->all();

        if (! in_array($answer, $allowed, true)) {
            throw ValidationException::withMessages(['answer' => [__('Invalid answer.')]]);
        }

        if ($survey->first_answer_at === null) {
            $survey->update([
                'first_question_key' => $firstKey,
                'first_answer_value' => $answer,
                'first_answer_at' => now(),
                'answers' => array_merge($survey->answers ?? [], [$firstKey => $answer]),
            ]);
        }

        return $survey->fresh();
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function submit(QuestJourneySurvey $survey, array $answers): QuestJourneySurvey
    {
        if ($survey->isExpired()) {
            throw ValidationException::withMessages(['survey' => [__('This survey has closed.')]]);
        }

        if ($survey->isSubmitted()) {
            return $survey;
        }

        $merged = array_merge($survey->answers ?? [], $answers);

        if ($survey->first_question_key && $survey->first_answer_value) {
            $merged[$survey->first_question_key] = $survey->first_answer_value;
        }

        $survey->update([
            'answers' => $merged,
            'submitted_at' => now(),
        ]);

        $this->processOperationalTriggers($survey->fresh());

        return $survey->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remainingSteps(QuestJourneySurvey $survey): array
    {
        $quest = $survey->quest()->with(['disputes', 'questCategory'])->first();
        $offer = $survey->quest_offer_id
            ? QuestOffer::query()->with('clarificationThread')->find($survey->quest_offer_id)
            : null;

        $steps = [];
        foreach ($this->questionsForCohort($survey->cohort) as $question) {
            if ($question['email_embedded'] ?? false) {
                continue;
            }

            if (! $this->questionApplies($question, $quest, $offer, $survey->answers ?? [])) {
                continue;
            }

            $steps[] = $this->normalizeStep($question);
        }

        return $steps;
    }

    public function captureUrl(QuestJourneySurvey $survey, string $answerValue): string
    {
        return URL::temporarySignedRoute(
            'journey-survey.capture',
            $survey->expires_at,
            ['token' => $survey->token, 'answer' => $answerValue],
        );
    }

    public function showUrl(QuestJourneySurvey $survey): string
    {
        return URL::temporarySignedRoute(
            'journey-survey.show',
            $survey->expires_at,
            ['token' => $survey->token],
        );
    }

    public function submitUrl(QuestJourneySurvey $survey): string
    {
        return URL::temporarySignedRoute(
            'journey-survey.submit',
            $survey->expires_at,
            ['token' => $survey->token],
        );
    }

    public function emailSubject(QuestJourneySurvey $survey, ?string $reminderKey = null): string
    {
        $title = $survey->quest?->title ?? 'your quest';

        if ($reminderKey) {
            $template = config("quest_journey_survey.reminder_copy.{$reminderKey}.subject", 'Reminder: your feedback on ":quest"');

            return str_replace(':quest', $title, $template);
        }

        $template = config("quest_journey_survey.cohorts.{$survey->cohort}.email_subject", 'Share your feedback');

        return str_replace(':quest', $title, $template);
    }

    public function emailOpener(QuestJourneySurvey $survey, ?string $reminderKey = null): string
    {
        $title = $survey->quest?->title ?? 'your quest';

        if ($reminderKey) {
            $template = config("quest_journey_survey.reminder_copy.{$reminderKey}.opener", '');

            return str_replace(':quest', $title, $template);
        }

        $template = config("quest_journey_survey.cohorts.{$survey->cohort}.email_opener", '');

        return str_replace(':quest', $title, $template);
    }

    public function emailHeadline(?string $reminderKey = null): string
    {
        if ($reminderKey) {
            return (string) config("quest_journey_survey.reminder_copy.{$reminderKey}.headline", 'We\'d love your feedback');
        }

        return 'We\'d love your feedback';
    }

    public function scheduleReminders(QuestJourneySurvey $survey): void
    {
        if ($survey->email_sent_at === null || $survey->isSubmitted()) {
            return;
        }

        $afterInitialHours = config('quest_journey_survey.reminders.after_initial_hours', [24, 72]);
        $beforeExpiryHours = (int) config('quest_journey_survey.reminders.before_expiry_hours', 6);

        foreach ($afterInitialHours as $hours) {
            $hours = (int) $hours;
            if ($hours <= 0) {
                continue;
            }

            $reminderKey = "{$hours}h";
            $sendAt = $survey->email_sent_at->copy()->addHours($hours);

            if ($sendAt->gte($survey->expires_at)) {
                continue;
            }

            $this->dispatchReminder($survey, $reminderKey, $sendAt);
        }

        if ($survey->expires_at) {
            $sendAt = $survey->expires_at->copy()->subHours($beforeExpiryHours);

            if ($sendAt->gt($survey->email_sent_at)) {
                $this->dispatchReminder($survey, 'before_expiry', $sendAt);
            }
        }
    }

    private function dispatchReminder(QuestJourneySurvey $survey, string $reminderKey, \Illuminate\Support\Carbon $sendAt): void
    {
        $sent = $survey->reminders_sent ?? [];
        if (in_array($reminderKey, $sent, true)) {
            return;
        }

        $job = \App\Jobs\SendQuestJourneySurveyReminderJob::dispatch($survey->id, $reminderKey);

        if ($sendAt->isFuture()) {
            $job->delay($sendAt);
        }
    }

    /**
     * @return list<array{value: string, label: string, url: string}>
     */
    public function emailEmbeddedOptions(QuestJourneySurvey $survey): array
    {
        $key = $this->firstQuestionKey($survey->cohort);

        return collect($this->optionsForQuestionKey($survey->cohort, $key))
            ->map(fn (array $opt) => [
                'value' => $opt['value'],
                'label' => $opt['label'],
                'url' => $this->captureUrl($survey, $opt['value']),
            ])
            ->values()
            ->all();
    }

    public function firstQuestionLabel(string $cohort): string
    {
        $key = $this->firstQuestionKey($cohort);
        foreach ($this->questionsForCohort($cohort) as $question) {
            if (($question['key'] ?? '') === $key) {
                return (string) ($question['label'] ?? '');
            }
        }

        return '';
    }

    private function createSurveyRecord(
        Quest $quest,
        int $userId,
        ?int $offerId,
        string $cohort,
        ?string $rejectionReason = null,
    ): ?QuestJourneySurvey {
        if (! config("quest_journey_survey.cohorts.{$cohort}")) {
            return null;
        }

        $existing = QuestJourneySurvey::query()
            ->where('quest_id', $quest->id)
            ->where('user_id', $userId)
            ->where('cohort', $cohort)
            ->first();

        if ($existing) {
            return $existing;
        }

        $ttlDays = (int) config('quest_journey_survey.link_ttl_days', 7);

        return QuestJourneySurvey::query()->create([
            'token' => (string) Str::uuid(),
            'quest_id' => $quest->id,
            'user_id' => $userId,
            'quest_offer_id' => $offerId,
            'cohort' => $cohort,
            'rejection_reason' => $rejectionReason,
            'expires_at' => now()->addDays($ttlDays),
        ]);
    }

    private function firstQuestionKey(string $cohort): string
    {
        return (string) config("quest_journey_survey.cohorts.{$cohort}.first_question_key");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function questionsForCohort(string $cohort): array
    {
        return config("quest_journey_survey.questions.{$cohort}", []);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function optionsForQuestionKey(string $cohort, string $key): array
    {
        foreach ($this->questionsForCohort($cohort) as $question) {
            if (($question['key'] ?? '') !== $key) {
                continue;
            }

            if (isset($question['options'])) {
                return $question['options'];
            }

            $type = $question['type'] ?? '';
            if ($type !== '' && config("quest_journey_survey.option_sets.{$type}")) {
                return config("quest_journey_survey.option_sets.{$type}");
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $answers
     */
    private function questionApplies(array $question, ?Quest $quest, ?QuestOffer $offer, array $answers): bool
    {
        if (($question['requires_dispute'] ?? false) && ! ($quest?->dispute_opened || $quest?->disputes()->exists())) {
            return false;
        }

        if (($question['requires_clarification'] ?? false)) {
            $hasClarify = $offer !== null
                && $offer->clarificationThread()->whereHas('messages')->exists();

            if (! $hasClarify) {
                return false;
            }
        }

        $showWhen = $question['show_when'] ?? null;
        if (is_array($showWhen)) {
            foreach ($showWhen as $depKey => $depValue) {
                $current = $answers[$depKey] ?? null;
                if ((string) $current !== (string) $depValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function normalizeStep(array $question): array
    {
        $type = $question['type'] ?? 'choice';
        $options = $question['options'] ?? null;

        if ($options === null && $type !== 'text' && $type !== 'nps') {
            $options = config("quest_journey_survey.option_sets.{$type}", []);
        }

        return [
            'key' => $question['key'],
            'type' => $type,
            'label' => $question['label'],
            'options' => $options ?? [],
            'optional' => (bool) ($question['optional'] ?? false),
            'max' => (int) ($question['max'] ?? 500),
            'show_when' => $question['show_when'] ?? null,
        ];
    }

    private function processOperationalTriggers(QuestJourneySurvey $survey): void
    {
        $answers = $survey->answers ?? [];

        if ($survey->cohort === 'client_completed') {
            $score = $this->scoreForAnswer('proposal_quality', $answers['proposal_quality'] ?? null);
            if ($score !== null && $score < 3) {
                $this->upsertOutreachItem(
                    'quest_journey_low_proposal_quality',
                    $survey->quest_id,
                    $survey->user_id,
                    null,
                    [
                        'proposal_quality_score' => $score,
                        'proposal_quality_answer' => $answers['proposal_quality'] ?? null,
                        'survey_id' => $survey->id,
                    ],
                    'high',
                    78,
                );
                $survey->update(['operational_flagged' => true]);
            }
        }

        if ($survey->cohort === 'freelancer_awarded') {
            $confidence = $answers['payment_confidence'] ?? null;
            if (in_array($confidence, ['somewhat_uncertain', 'not_confident'], true)) {
                $this->upsertOutreachItem(
                    'freelancer_low_payment_confidence',
                    $survey->quest_id,
                    $survey->user_id,
                    $survey->quest_offer_id,
                    [
                        'payment_confidence' => $confidence,
                        'survey_id' => $survey->id,
                    ],
                    'high',
                    76,
                );
                $survey->update(['operational_flagged' => true]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function upsertOutreachItem(
        string $situationKey,
        ?int $questId,
        ?int $userId,
        ?int $offerId,
        array $context,
        string $priority,
        int $score,
    ): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('staff_proactive_outreach_items')) {
            return;
        }

        $fingerprint = hash('sha256', implode(':', array_filter([
            $situationKey,
            $userId,
            $questId,
            $offerId,
        ])));

        $meta = config("operations.proactive_outreach.situations.{$situationKey}", []);
        $existing = StaffProactiveOutreachItem::query()->where('fingerprint', $fingerprint)->first();

        if ($existing && in_array($existing->status, ['resolved', 'auto_resolved'], true)) {
            return;
        }

        $attributes = [
            'situation_key' => $situationKey,
            'status' => $existing?->status === 'contacted' ? 'contacted' : 'open',
            'priority' => $priority,
            'priority_score' => $score,
            'target_user_id' => $userId,
            'quest_id' => $questId,
            'quest_offer_id' => $offerId,
            'context' => $context,
            'suggested_template_slug' => $meta['default_template_slug'] ?? null,
            'detected_at' => $existing?->detected_at ?? now(),
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return;
        }

        StaffProactiveOutreachItem::query()->create([
            ...$attributes,
            'fingerprint' => $fingerprint,
        ]);
    }

    private function scoreForAnswer(string $questionKey, ?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return config("quest_journey_survey.score_maps.{$questionKey}.{$value}");
    }
}
