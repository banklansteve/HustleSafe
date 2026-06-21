<?php

namespace Tests\Unit;

use App\Models\Quest;
use App\Services\Quest\QuestCompletionScheduleService;
use Carbon\Carbon;
use Tests\TestCase;

class QuestCompletionScheduleServiceTest extends TestCase
{
    public function test_hard_deadline_takes_precedence_over_planned_finish_for_engagement(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $quest = new Quest([
            'estimated_delivery_date' => '2026-06-20',
            'delivery_deadline' => '2026-06-25',
        ]);

        $service = app(QuestCompletionScheduleService::class);

        $this->assertSame('2026-06-20', $service->plannedFinishDate($quest)?->toDateString());
        $this->assertSame('2026-06-25', $service->hardDeadlineDate($quest)?->toDateString());
        $this->assertSame('2026-06-25', $service->engagementAnchorAt($quest)?->toDateString());
    }

    public function test_planned_finish_only_quest_remains_backward_compatible(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        $quest = new Quest([
            'estimated_delivery_date' => '2026-06-22',
        ]);

        $service = app(QuestCompletionScheduleService::class);

        $this->assertSame('2026-06-22', $service->engagementAnchorAt($quest)?->toDateString());
        $this->assertNull($service->hardDeadlineDate($quest));
    }

    public function test_initial_due_at_prefers_hard_deadline_then_planned_finish(): void
    {
        $service = app(QuestCompletionScheduleService::class);

        $hard = $service->initialDueAtFromCreateData([
            'estimated_completion_days' => 14,
            'estimated_delivery_date' => '2026-07-01',
            'delivery_deadline' => '2026-07-10',
        ]);

        $this->assertSame('2026-07-10', $hard->toDateString());
    }
}
