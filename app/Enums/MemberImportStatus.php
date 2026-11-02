<?php

namespace App\Enums;

enum MemberImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Processing => __('Importing'),
            self::Completed => __('Finished'),
            self::Failed => __('Failed'),
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
