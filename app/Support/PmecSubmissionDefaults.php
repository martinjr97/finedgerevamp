<?php

namespace App\Support;

final class PmecSubmissionDefaults
{
    public const LGART = '8000';

    public const EMFSL = 'F021';

    public const ZLSCH = 'E';

    public const MODE_NEW_LOANS = 'new_loans';

    public const MODE_FAILED_MISSED = 'failed_missed';

    public const MODE_MANUAL = 'manual';

    public const SUBMISSION_STATUS_DRAFT = 'draft';

    public const SUBMISSION_STATUS_GENERATED = 'generated';

    public const SUBMISSION_STATUS_SUBMITTED = 'submitted';

    public const SUBMISSION_STATUS_FAILED = 'failed';

    public const ITEM_STATUS_GENERATED = 'generated';

    public const ITEM_STATUS_SUBMITTED = 'submitted';

    public const ITEM_STATUS_FAILED = 'failed';

    public const ITEM_STATUS_RESUBMITTED = 'resubmitted';

    /**
     * @return array<string, string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_NEW_LOANS => 'New loans only',
            self::MODE_FAILED_MISSED => 'Include failed/missed previous submissions',
            self::MODE_MANUAL => 'Manually selected loans',
        ];
    }

    /**
     * @return list<string>
     */
    public static function modeValues(): array
    {
        return array_keys(self::modes());
    }

    /**
     * @return array<string, string>
     */
    public static function submissionStatuses(): array
    {
        return [
            self::SUBMISSION_STATUS_DRAFT => 'Draft',
            self::SUBMISSION_STATUS_GENERATED => 'Generated',
            self::SUBMISSION_STATUS_SUBMITTED => 'Submitted',
            self::SUBMISSION_STATUS_FAILED => 'Failed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function itemStatuses(): array
    {
        return [
            self::ITEM_STATUS_GENERATED => 'Generated',
            self::ITEM_STATUS_SUBMITTED => 'Submitted',
            self::ITEM_STATUS_FAILED => 'Failed',
            self::ITEM_STATUS_RESUBMITTED => 'Resubmitted',
        ];
    }

    /**
     * @return list<string>
     */
    public static function excelHeadings(): array
    {
        return [
            'PERNR',
            'LGART',
            'ENDDA',
            'BEGDA',
            'BETRG',
            'EMFSL',
            'ZLSCH',
            'NRC. NO',
            'FIRST NAME',
            'SURNAME',
        ];
    }
}
