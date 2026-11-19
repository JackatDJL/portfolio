<?php

namespace App\Support;

use RuntimeException;

final class ContentSyncFailure extends RuntimeException
{
    /** @param list<string> $conflictingPaths */
    public function __construct(
        string $message,
        public readonly array $conflictingPaths = [],
        public readonly bool $recoveryRequired = false,
    ) {
        parent::__construct($message);
    }
}
