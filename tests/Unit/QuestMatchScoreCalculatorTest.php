<?php

namespace Tests\Unit;

use App\Enums\QuestFreelancerLocationPref;
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

    public function test_remote_friendly_quest_scores_same_for_near_and_far_freelancers(): void
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
            'required_skills' => ['python', 'tutoring'],
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
        ]);

        $farQuest = new Quest([
            'state_id' => 2,
            'local_government_id' => 99,
            'budget_amount_minor' => 400_000_00,
            'required_skills' => ['python', 'tutoring'],
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
        ]);

        $metrics = new FreelancerMetric([
            'skills_list' => ['python', 'tutoring'],
            'typical_job_value_minor' => 380_000_00,
            'verification_level' => 4,
            'average_rating' => 4.6,
            'completion_rate' => 90,
            'last_proposal_at' => now()->subDays(2),
        ]);

        $local = $calculator->score($freelancer, $localQuest, $metrics);
        $far = $calculator->score($freelancer, $farQuest, $metrics);

        $this->assertSame('remote', $local['location_tier']);
        $this->assertSame('remote', $far['location_tier']);
        $this->assertEqualsWithDelta($local['total'], $far['total'], 0.01);
    }

    public function test_remote_friendly_quest_prioritizes_skills_over_local_proximity(): void
    {
        $verification = Mockery::mock(VerificationEngineService::class);
        $verification->shouldReceive('effectiveLevel')->andReturn(4);
        $this->app->instance(VerificationEngineService::class, $verification);

        $calculator = app(QuestMatchScoreCalculator::class);

        $localFreelancer = new User([
            'state_id' => 1,
            'local_government_id' => 10,
        ]);

        $remoteFreelancer = new User([
            'state_id' => 2,
            'local_government_id' => 99,
        ]);

        $quest = new Quest([
            'state_id' => 1,
            'local_government_id' => 10,
            'budget_amount_minor' => 400_000_00,
            'required_skills' => ['python', 'tutoring', 'online teaching'],
            'freelancer_location_pref' => QuestFreelancerLocationPref::RemoteFriendly,
        ]);

        $strongRemoteMetrics = new FreelancerMetric([
            'skills_list' => ['python', 'tutoring', 'online teaching'],
            'typical_job_value_minor' => 380_000_00,
            'verification_level' => 4,
            'average_rating' => 4.8,
            'completion_rate' => 95,
            'last_proposal_at' => now()->subDays(1),
        ]);

        $weakLocalMetrics = new FreelancerMetric([
            'skills_list' => ['python'],
            'typical_job_value_minor' => 380_000_00,
            'verification_level' => 3,
            'average_rating' => 4.0,
            'completion_rate' => 70,
            'last_proposal_at' => now()->subDays(20),
        ]);

        $remote = $calculator->score($remoteFreelancer, $quest, $strongRemoteMetrics);
        $local = $calculator->score($localFreelancer, $quest, $weakLocalMetrics);

        $this->assertGreaterThan($local['total'], $remote['total']);
    }
}
