<?php declare(strict_types=1);

namespace Digibit\Audit\Diff;

enum ChangeAction: string {
    case Added    = 'added';
    case Removed  = 'removed';
    case Modified = 'modified';
    case Nested   = 'nested';

    /** A discrete event with no before/after value, recorded via ChangeRecorder::recordEvent(). */
    case Occurred = 'occurred';
}