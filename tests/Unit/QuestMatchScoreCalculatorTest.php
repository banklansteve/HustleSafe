<?php

namespace Tests\Unit;

use App\Models\FreelancerMetric;
use App\Models\Quest;
use App\Models\User;
use App\Services\Matching\QuestMatchScoreCalculator;
use App\Services\Verification\VerificationEngineService;
use Mockery;
use Tests\TestCase;

class QuestMatchScoreCalculatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_same_lga_scores_higher_than_different_state(): void
    {
        $verification = Mockery::mock(VerificationEngineService::class);
        $verification->shouldReceive('effectiveLevel')->andReturn(4);
        $this->app->instance(VerificationEngineService::class, $verification);

        $calculator = app(QuestMatchScoreCalculator::class);

        $freelancer = new User([
            'state_id' => 1,
            'local_government_id' => 10,
        ]);

        $localQuest = new Quest([
            'state_id' => 1,
            'local_government_id' => 10,
            'budget_amount_minor' => 400_000_00,
            'required_skills' => [],
        ]);

        $farQuest = new Quest([
            'state_id' => 2,
            'local_government_id' => 99,
            'budget_amount_minor' => 400_000_00,
            'required_skills' => [],
        ]);

        $metrics = new FreelancerMetric([
            'typical_job_value_minor' => 380_000_00,
            'verification_level' => 4,
            'average_rating' => 4.6,
            'completion_rate' => 90,
            'last_proposal_at' => now()->subDays(2),
        ]);

        $local = $calculator->score($freelancer, $localQuest, $metrics);
        $far = $calculator->score($freelancer, $farQuest, $metrics);

        $this->assertGreaterThan($far['total'], $local['total']);
        $this->assertSame('same_lga', $local['location_tier']);
        $this->assertSame('different_state', $far['location_tier']);
    }

    public function test_skills_gate_blocks_low_overlap(): void
    {
        $verification = Mockery::mock(VerificationEngineService::class);
        $verification->shouldReceive('effectiveLevel')->andReturn(3);
        $this->app->instance(VerificationEngineService::class, $verification);

        $calculator = app(QuestMatchScoreCalculator::class);

        $freelancer = new User(['state_id' => 1, 'local_government_id' => 1]);
        $quest = new Quest([
            'state_id' => 1,
            'local_government_id' => 1,
            'required_skills' => ['react', 'node', 'postgres'],
        ]);

        $metrics = new FreelancerMetric([
            'skills_list' => ['react'],
            'verification_level' => 3,
        ]);

        $result = $calculator->score($freelancer, $quest, $metrics);

        $this->assertFalse($result['passes_skills_gate']);
    }
}
