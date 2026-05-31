<?php

namespace Tests\Feature;

use App\Models\Quest;
use App\Models\QuestJourneySurvey;
use App\Models\User;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class QuestJourneySurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_first_answer_and_submit_remaining(): void
    {
        $client = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id, 'title' => 'Logo design']);

        $survey = app(QuestJourneySurveyService::class)->createAndSchedule(
            quest: $quest,
            userId: $client->id,
            offerId: null,
            cohort: 'client_completed',
        );

        $this->assertNotNull($survey);
        $this->assertNull($survey->email_sent_at);

        app(QuestJourneySurveyService::class)->captureFirstAnswer($survey, 'satisfied');

        $survey->refresh();
        $this->assertSame('satisfied', $survey->first_answer_value);
        $this->assertSame('proposal_quality', $survey->first_question_key);

        $steps = app(QuestJourneySurveyService::class)->remainingSteps($survey);
        $this->assertNotEmpty($steps);
        $this->assertFalse(collect($steps)->contains(fn (array $s) => ($s['key'] ?? '') === 'proposal_quality'));

        $answers = [];
        foreach ($steps as $step) {
            if (($step['optional'] ?? false) || ($step['type'] ?? '') === 'text') {
                continue;
            }
            if (($step['type'] ?? '') === 'nps') {
                continue;
            }
            if (isset($step['show_when'])) {
                continue;
            }
            $answers[$step['key']] = $step['options'][0]['value'] ?? 'yes';
        }
        $answers['support_needed'] = 'no';
        $answers['post_again_likelihood'] = 'probably';

        app(QuestJourneySurveyService::class)->submit($survey, $answers);

        $survey->refresh();
        $this->assertNotNull($survey->submitted_at);
    }

    public function test_schedule_reminders_after_initial_email(): void
    {
        $client = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id]);

        $survey = QuestJourneySurvey::query()->create([
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'quest_id' => $quest->id,
            'user_id' => $client->id,
            'cohort' => 'client_completed',
            'expires_at' => now()->addDays(7),
            'email_sent_at' => now(),
        ]);

        \Illuminate\Support\Facades\Queue::fake();

        app(QuestJourneySurveyService::class)->scheduleReminders($survey);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendQuestJourneySurveyReminderJob::class, 3);
    }

    public function test_expired_survey_capture_is_rejected(): void
    {
        $client = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id]);

        $survey = QuestJourneySurvey::query()->create([
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'quest_id' => $quest->id,
            'user_id' => $client->id,
            'cohort' => 'client_completed',
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(QuestJourneySurveyService::class)->captureFirstAnswer($survey, 'neutral');
    }
}
