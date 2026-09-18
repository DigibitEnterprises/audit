<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Diff\Change;
use Digibit\Audit\Diff\ChangeAction;
use Digibit\Audit\Diff\ChangeSet;

/**
 * Thin service for writing audit log entries.
 * Inject this into controllers — do NOT inject into entities.
 */
class AuditLogger
{
    public function __construct(
        private StorageCollection $storage
    ) {}

    /** @return AuditLog[] flat list of every row created (roots + all descendants) */
    public function logFieldChanges(object $entity, ChangeSet $changeset): array
    {
        $logs = [];
        $this->walk($entity, $changeset, null, $logs);
        return $logs;
    }

    private function walk(object $entity, ChangeSet $changeset, ?AuditLog $parent, array &$logs): void
    {
        foreach ($changeset->all() as $key => $change) {
            if ($change->action === ChangeAction::Nested) {
                $node = AuditLog::nested($entity, (string) $key, $change->path);
                $this->attach($node, $parent, $logs);
                $this->walk($entity, $change->nested, $node, $logs);
                continue;
            }

            $this->attach($this->persistFieldChange($entity, (string) $key, $change), $parent, $logs);
        }
    }

    private function attach(?AuditLog $log, ?AuditLog $parent, array &$logs): void
    {
        if ($log === null) {
            return;
        }
        if ($parent !== null) {
            $parent->addChild($log);
        }
        $this->storage->add($log);
        $logs[] = $log;
    }

    private function persistFieldChange(object $entity, string $field, Change $change): ?AuditLog
    {
        $oldStr = $this->stringify($change->original);
        $newStr = $this->stringify($change->modified);

        return match ($change->action) {
            ChangeAction::Modified => AuditLog::fieldChange($entity, $field, $change->path, $oldStr ?? '', $newStr ?? ''),
            ChangeAction::Added    => AuditLog::fieldAdded($entity, $field, $change->path, $newStr ?? ''),
            ChangeAction::Removed  => AuditLog::fieldRemoved($entity, $field, $change->path, $oldStr ?? ''),
            ChangeAction::Nested   => null, // unreachable — handled in walk()
        };
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}
