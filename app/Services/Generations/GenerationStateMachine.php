<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;

/**
 * Provider events may arrive late or out of order. Status only moves forward
 * through pending -> queued -> processing -> succeeded|failed; a terminal
 * generation is never regressed by a stale event.
 */
class GenerationStateMachine
{
    public function canAdvance(GenerationStatus $from, GenerationStatus $to): bool
    {
        if ($from->isTerminal()) {
            return false;
        }

        return $to->rank() > $from->rank();
    }

    public function isLegalTransition(GenerationStatus $from, GenerationStatus $to): bool
    {
        return match ($from) {
            GenerationStatus::Pending => in_array($to, [GenerationStatus::Queued, GenerationStatus::Processing, GenerationStatus::Succeeded, GenerationStatus::Failed], true),
            GenerationStatus::Queued => in_array($to, [GenerationStatus::Processing, GenerationStatus::Succeeded, GenerationStatus::Failed], true),
            GenerationStatus::Processing => in_array($to, [GenerationStatus::Succeeded, GenerationStatus::Failed], true),
            GenerationStatus::Succeeded => $to === GenerationStatus::Succeeded,
            GenerationStatus::Failed => $to === GenerationStatus::Failed,
        };
    }
}
