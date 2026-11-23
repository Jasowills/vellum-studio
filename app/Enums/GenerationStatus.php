<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Confirming',
            self::Queued => 'Queued',
            self::Processing => 'In the studio',
            self::Succeeded => 'Ready',
            self::Failed => 'Needs attention',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending, self::Queued => 'waiting',
            self::Processing => 'working',
            self::Succeeded => 'success',
            self::Failed => 'danger',
        };
    }
}
