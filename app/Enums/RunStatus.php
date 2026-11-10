<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Profiling = 'profiling';
    case Profiled = 'profiled';
    case Cleaning = 'cleaning';
    case Cleaned = 'cleaned';
    case Validating = 'validating';
    case Validated = 'validated';
    case ValidationFailed = 'validation_failed';
    case Exporting = 'exporting';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * Statuses in which the run has a profiled dataset at rest (no job in flight).
     *
     * @return array<int, self>
     */
    public static function settled(): array
    {
        return [self::Profiled, self::Cleaned, self::Validated, self::ValidationFailed, self::Delivered];
    }

    /**
     * Whether the run's data can be (re-)cleaned in this status.
     */
    public function canClean(): bool
    {
        return in_array($this, self::settled(), true);
    }

    /**
     * Whether the run's data can be validated in this status.
     */
    public function canValidate(): bool
    {
        return in_array($this, self::settled(), true);
    }

    /**
     * Whether the run can be exported in this status, provided an override reason
     * is supplied when the current data has not passed validation.
     */
    public function canExport(): bool
    {
        return in_array($this, self::settled(), true);
    }
}
