<?php declare(strict_types=1);

namespace Digibit\Audit;

interface AuditStore
{
    /**
     * Persists a batch of audit entries produced from one successful record() call.
     * Always called as a batch — implementations may flush immediately or defer.
     *
     * @param list<AuditEntry> $entries
     */
    public function persist(array $entries): void;
}
