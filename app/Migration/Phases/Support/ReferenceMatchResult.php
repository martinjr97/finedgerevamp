<?php

namespace App\Migration\Phases\Support;

use Illuminate\Database\Eloquent\Model;

class ReferenceMatchResult
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_CONFLICT = 'conflict';

    public const STATUS_UNMATCHED = 'unmatched';

    /**
     * @param  list<int>  $candidateTargetIds
     */
    public function __construct(
        public readonly string $status,
        public readonly ?Model $target = null,
        public readonly array $candidateTargetIds = [],
        public readonly ?string $reason = null,
        public readonly string $method = '',
    ) {}

    public static function matched(Model $target, string $method = 'matched_existing'): self
    {
        return new self(self::STATUS_MATCHED, $target, [], null, $method);
    }

    /**
     * @param  list<int>  $candidateTargetIds
     */
    public static function conflict(string $reason, array $candidateTargetIds, string $method = 'ambiguous_match'): self
    {
        return new self(self::STATUS_CONFLICT, null, $candidateTargetIds, $reason, $method);
    }

    public static function unmatched(string $reason = 'no_target_match'): self
    {
        return new self(self::STATUS_UNMATCHED, null, [], $reason, 'unmatched');
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    public function isConflict(): bool
    {
        return $this->status === self::STATUS_CONFLICT;
    }
}
