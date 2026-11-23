<?php

namespace Tests\Unit;

use App\Enums\GenerationStatus;
use App\Services\Generations\GenerationStateMachine;
use PHPUnit\Framework\TestCase;

class GenerationStateMachineTest extends TestCase
{
    public function test_it_only_advances_status_forward(): void
    {
        $states = new GenerationStateMachine;

        $this->assertTrue($states->canAdvance(GenerationStatus::Pending, GenerationStatus::Queued));
        $this->assertTrue($states->canAdvance(GenerationStatus::Queued, GenerationStatus::Processing));
        $this->assertTrue($states->canAdvance(GenerationStatus::Processing, GenerationStatus::Succeeded));
        $this->assertTrue($states->canAdvance(GenerationStatus::Pending, GenerationStatus::Failed));
        $this->assertFalse($states->canAdvance(GenerationStatus::Processing, GenerationStatus::Queued));
        $this->assertFalse($states->canAdvance(GenerationStatus::Succeeded, GenerationStatus::Failed));
        $this->assertFalse($states->canAdvance(GenerationStatus::Failed, GenerationStatus::Succeeded));
    }

    public function test_it_accepts_legal_transitions_and_same_state_replays(): void
    {
        $states = new GenerationStateMachine;

        $this->assertTrue($states->isLegalTransition(GenerationStatus::Pending, GenerationStatus::Processing));
        $this->assertTrue($states->isLegalTransition(GenerationStatus::Succeeded, GenerationStatus::Succeeded));
        $this->assertTrue($states->isLegalTransition(GenerationStatus::Failed, GenerationStatus::Failed));
        $this->assertFalse($states->isLegalTransition(GenerationStatus::Queued, GenerationStatus::Pending));
    }
}
