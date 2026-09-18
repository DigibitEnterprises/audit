<?php declare(strict_types=1);

namespace Digibit\Audit;

/**
 * The outcome of a ChangeRecorder::record() call.
 *
 * Expected control-flow states only — unexpected failures (snapshot errors,
 * persist errors) are thrown as exceptions, not captured here.
 *
 * Mutation callable contract:
 *   true              → proceed with diff and persist
 *   false             → abort recording silently
 *   string|\Throwable → abort recording with reason (accessible via $error)
 */
class RecorderResult
{
    protected function __construct(
        public readonly bool                   $aborted,
        public readonly bool                   $changed,
        public readonly string|\Throwable|null $error,
    ) {}

    // ## Named constructors

    public static function changed(): static
    {
        return new static(aborted: false, changed: true, error: null);
    }

    public static function unchanged(): static
    {
        return new static(aborted: false, changed: false, error: null);
    }

    public static function aborted(string|\Throwable|null $reason = null): static
    {
        return new static(aborted: true, changed: false, error: $reason);
    }

    // ## Convenience

    public function successful(): bool
    {
        return !$this->aborted && $this->error === null;
    }
}
